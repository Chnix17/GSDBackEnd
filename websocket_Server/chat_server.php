<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

class Chat implements MessageComponentInterface {
    protected $clients;
    private $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        
        $this->db = new \mysqli('localhost', 'root', '', 'dbgsd');

        if ($this->db->connect_error) {
            die("Connection failed: " . $this->db->connect_error);
        }
        echo "Database connection successful\n";
        echo "Chat server build: assoc-array validation enabled\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        // Trace raw incoming message (first 500 chars max)
        $logMsg = substr(is_string($msg) ? $msg : json_encode($msg), 0, 500);
        @error_log("WS IN [{$from->resourceId}]: $logMsg\n", 3, __DIR__ . '/chat_server.log');

        // Validate incoming data to prevent errors on non-chat messages
        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            echo "Invalid JSON received from {$from->resourceId}\n";
            @error_log("Invalid JSON from {$from->resourceId}\n", 3, __DIR__ . '/chat_server.log');
            return;
        }
        if (!is_array($data)) {
            echo "Invalid payload type from {$from->resourceId}\n";
            @error_log("Invalid payload type from {$from->resourceId}\n", 3, __DIR__ . '/chat_server.log');
            return;
        }

        // Optional protocol: registration message to bind a connection to a user_id
        $type = isset($data['type']) ? (string)$data['type'] : '';
        if ($type === 'register') {
            $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
            if ($userId > 0) {
                // Store metadata on this connection
                $this->clients[$from] = ['user_id' => $userId];
                echo "Registered connection {$from->resourceId} as user {$userId}\n";
                @error_log("Registered {$from->resourceId} as user {$userId}\n", 3, __DIR__ . '/chat_server.log');
            } else {
                echo "Invalid register payload from {$from->resourceId}\n";
                @error_log("Invalid register payload from {$from->resourceId}\n", 3, __DIR__ . '/chat_server.log');
            }
            return;
        }

        // Only process chat messages
        if ($type !== 'chat_message') {
            echo "Ignoring non-chat message type '{$type}' from {$from->resourceId}\n";
            @error_log("Ignoring non-chat message type '{$type}' from {$from->resourceId}\n", 3, __DIR__ . '/chat_server.log');
            return;
        }

        $sender_id = isset($data['sender_id']) ? (int)$data['sender_id'] : 0;
        // Fallback: if sender_id missing, try to use registered connection user_id
        if ($sender_id <= 0 && isset($this->clients[$from]) && is_array($this->clients[$from]) && isset($this->clients[$from]['user_id'])) {
            $sender_id = (int)$this->clients[$from]['user_id'];
        }
        $receiver_id = isset($data['receiver_id']) ? (int)$data['receiver_id'] : 0;
        $message = isset($data['message']) ? trim((string)$data['message']) : '';

        if ($sender_id <= 0 || $receiver_id <= 0 || $message === '') {
            echo "Missing/invalid fields from {$from->resourceId} - sender_id: {$sender_id}, receiver_id: {$receiver_id}, message length: " . strlen($message) . "\n";
            @error_log("Missing/invalid fields from {$from->resourceId} (s:$sender_id r:$receiver_id len:" . strlen($message) . ")\n", 3, __DIR__ . '/chat_server.log');
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO tbl_chat (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        if (!$stmt) {
            echo "Prepare failed: {$this->db->error}\n";
            @error_log("DB prepare failed: {$this->db->error}\n", 3, __DIR__ . '/chat_server.log');
            return;
        }
        $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
        
        if ($stmt->execute()) {
            echo "Message saved to database\n";

            // Non-blocking audit logging for chat message insertion
            try {
                // Build sender and receiver full names (First + Middle initial + Last)
                $fullNameSql = "SELECT CONCAT(\n                                    users_fname,\n                                    CASE WHEN users_mname IS NOT NULL AND users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.') ELSE '' END,\n                                    ' ', users_lname\n                                 ) AS full_name\n                               FROM tbl_users WHERE users_id = ?";

                $sender_name = null; $receiver_name = null;
                if ($ns = $this->db->prepare($fullNameSql)) {
                    $ns->bind_param("i", $sender_id);
                    $ns->execute();
                    $ns->bind_result($sender_name);
                    $ns->fetch();
                    $ns->close();
                }
                if ($nr = $this->db->prepare($fullNameSql)) {
                    $nr->bind_param("i", $receiver_id);
                    $nr->execute();
                    $nr->bind_result($receiver_name);
                    $nr->fetch();
                    $nr->close();
                }

                $sender_name = $sender_name ?: ('User #' . (int)$sender_id);
                $receiver_name = $receiver_name ?: ('User #' . (int)$receiver_id);

                $snippet = substr((string)$message, 0, 200);
                $desc = "User {$sender_name} sent a message to {$receiver_name}: '" . $this->db->real_escape_string($snippet) . "'";

                if ($al = $this->db->prepare("INSERT INTO audit_log (description, action, created_at, created_by) VALUES (?, ?, NOW(), ?)")) {
                    $action = 'SEND MESSAGE';
                    $al->bind_param("ssi", $desc, $action, $sender_id);
                    $al->execute();
                    $al->close();
                }
            } catch (\Throwable $e) { /* ignore audit logging errors */ }

            // Send push notification to receiver
            $pushUrl = 'http://localhost/coc/gsd/send-push-notification.php';
            echo "--- Building Push URL ---\n";
            echo "Value of \$_SERVER['HTTP_HOST']: " . ($_SERVER['HTTP_HOST'] ?? '[not set]') . "\n";
            echo "Value of \$_SERVER['REQUEST_URI']: " . ($_SERVER['REQUEST_URI'] ?? '[not set]') . "\n";
            echo "Constructed URL: $pushUrl\n";
            echo "---------------------------\n";

            $pushData = [
                'operation' => 'send',
                'user_id' => $receiver_id,
                'title' => 'New Message',
                'body' => $message,
                'data' => [
                    'sender_id' => $sender_id,
                    'message' => $message,
                    'type' => 'chat_message',
                    'timestamp' => time()
                ]
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $pushUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($pushData))
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            echo "Push notification response: $response\n";

            if ($error || $httpCode < 200 || $httpCode >= 300) {
                echo "Push notification failed for chat receiver $receiver_id: " . ($error ?: "HTTP $httpCode") . "\n";
            } else {
                echo "Push notification sent successfully to chat receiver $receiver_id\n";
            }
        } else {
            echo "Error saving message: {$stmt->error}\n";
        }
        $stmt->close();

        // Broadcast sanitized payload to all clients
        $broadcast = [
            'sender_id'   => $sender_id,
            'receiver_id' => $receiver_id,
            'message'     => $message,
            'message_id'  => isset($data['message_id']) ? (string)$data['message_id'] : (string)time(),
            'timestamp'   => isset($data['timestamp']) ? (string)$data['timestamp'] : date(DATE_ISO8601),
        ];
        $out = json_encode($broadcast);
        @error_log("WS OUT [{$from->resourceId}]: $out\n", 3, __DIR__ . '/chat_server.log');
        foreach ($this->clients as $client) {
            $client->send($out);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8080
);

echo "WebSocket server running at ws://localhost:8080\n";
$server->run(); 