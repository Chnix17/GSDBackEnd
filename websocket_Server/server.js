const WebSocket = require('ws');
const mysql = require('mysql2');

// Create database connection pool instead of single connection
const pool = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'dbgsd',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// WebSocket server configuration
const wss = new WebSocket.Server({ 
  port: 8080,
  // Add ping timeout
  clientTracking: true,
});

// Heartbeat function to keep connections alive
function heartbeat() {
  this.isAlive = true;
}

wss.on('connection', (ws) => {
  console.log('New client connected');
  
  // Set up heartbeat
  ws.isAlive = true;
  ws.on('pong', heartbeat);

  // Handle messages
  ws.on('message', (data) => {
    try {
      const { sender_id, receiver_id, message } = JSON.parse(data);
      
      pool.query(
        'INSERT INTO tbl_chat (sender_id, receiver_id, message) VALUES (?, ?, ?)',
        [sender_id, receiver_id, message],
        (err) => {
          if (err) {
            console.error('Database error:', err);
            ws.send(JSON.stringify({ error: 'Failed to save message' }));
            return;
          }

          wss.clients.forEach(client => {
            if (client.readyState === WebSocket.OPEN) {
              client.send(JSON.stringify({ sender_id, receiver_id, message }));
            }
          });
        }
      );
    } catch (error) {
      console.error('Message handling error:', error);
      ws.send(JSON.stringify({ error: 'Invalid message format' }));
    }
  });

  // Handle client disconnect
  ws.on('close', () => {
    console.log('Client disconnected');
  });

  // Handle errors
  ws.on('error', (error) => {
    console.error('WebSocket error:', error);
  });
});

// Set up interval to check for stale connections
const interval = setInterval(() => {
  wss.clients.forEach((ws) => {
    if (ws.isAlive === false) {
      console.log('Terminating stale connection');
      return ws.terminate();
    }
    
    ws.isAlive = false;
    ws.ping(() => {});
  });
}, 30000);

// Clean up on server close
wss.on('close', () => {
  clearInterval(interval);
});

// Handle server errors
wss.on('error', (error) => {
  console.error('WebSocket server error:', error);
});

console.log("WebSocket server running at ws://localhost:8080");
