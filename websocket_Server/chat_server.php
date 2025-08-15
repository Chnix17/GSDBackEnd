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
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg);

        // Validate incoming data to prevent errors on non-chat messages

        
        $sender_id = $data->sender_id;
        $receiver_id = $data->receiver_id;
        $message = $data->message;

        $stmt = $this->db->prepare("INSERT INTO tbl_chat (sender_id, receiver_id, message) VALUES (?, ?, ?)");
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

        foreach ($this->clients as $client) {
            $client->send($msg);
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