# GSD Push Notification System

This system provides real-time push notifications for approval notifications in the GSD (General Services Department) application.

## Components

### 1. WebSocket Server (`notification_server.php`)
- Runs on port 8081
- Handles WebSocket connections from clients
- Manages client registration and authentication
- Forwards notifications to appropriate clients

### 2. HTTP Trigger Server (`notification_trigger.php`)
- Runs on port 8082
- Receives HTTP POST requests to trigger notifications
- Forwards notifications to WebSocket server
- Handles CORS and request validation

### 3. Push Notification Service (`push_notification.php`)
- Simple PHP service to send notifications
- Uses cURL to send HTTP requests to trigger server
- Can be easily integrated into existing code

### 4. JavaScript Client (`notification_client.js`)
- WebSocket client for browsers
- Handles connection, reconnection, and heartbeat
- Shows browser notifications
- Provides callback system for custom handling

## Setup Instructions

### 1. Install Dependencies

Make sure you have OpenSwoole installed:
```bash
pecl install openswoole
```

### 2. Start the WebSocket Server

```bash
cd websocket_Server
php notification_server.php
```

The server will start on `ws://localhost:8081`

### 3. Start the HTTP Trigger Server

```bash
cd websocket_Server
php notification_trigger.php
```

The server will start on `http://localhost:8082`

### 4. Test the System

Open `test_notifications.html` in your browser to test the push notification system.

## Integration with Existing Code

### 1. Include the Push Notification Service

Add this to your PHP files where you want to send notifications:

```php
require_once 'push_notification.php';
$pushService = new PushNotificationService();
```

### 2. Send Notifications

#### Approval Notifications
```php
$pushService->sendApprovalNotification(
    $departmentId,    // Department ID
    $userLevelId,     // User Level ID
    $message,         // Notification message
    $additionalData   // Additional data (optional)
);
```

#### User Notifications
```php
$pushService->sendUserNotification(
    $userId,          // User ID
    $message,         // Notification message
    $additionalData   // Additional data (optional)
);
```

### 3. Client-Side Integration

Include the JavaScript client in your HTML:

```html
<script src="websocket_Server/notification_client.js"></script>
```

Initialize the client:

```javascript
const notificationClient = new NotificationClient();

// Request notification permission
notificationClient.requestPermission();

// Connect with user details
notificationClient.connect(userId, departmentId, userLevelId);

// Handle notifications
notificationClient.onNotification((type, message) => {
    console.log(`Received ${type} notification:`, message);
    
    // Update UI or navigate to relevant page
    if (message.data && message.data.reservation_id) {
        // Navigate to reservation details
        window.location.href = `/reservation-details.php?id=${message.data.reservation_id}`;
    }
});
```

## Usage Examples

### 1. Sending Approval Notifications

When a new reservation is created and needs approval:

```php
// In your reservation creation code
$pushService = new PushNotificationService();
$pushService->sendApprovalNotification(
    27,  // Department ID
    1,   // User Level ID (approvers)
    "New reservation request pending approval",
    [
        'reservation_id' => $reservationId,
        'requester_name' => $requesterName,
        'action' => 'approve'
    ]
);
```

### 2. Sending User Notifications

When a reservation status changes:

```php
$pushService->sendUserNotification(
    $userId,
    "Your reservation has been approved",
    [
        'reservation_id' => $reservationId,
        'status' => 'approved'
    ]
);
```

### 3. Browser Notifications

The JavaScript client will automatically show browser notifications if permission is granted.

## Configuration

### Ports
- WebSocket Server: 8081
- HTTP Trigger Server: 8082

### Database
The system uses the existing database connection from your application.

### Security
- Clients must register with valid user_id, department_id, and user_level_id
- Notifications are filtered based on user permissions
- CORS is enabled for cross-origin requests

## Troubleshooting

### 1. WebSocket Connection Issues
- Check if OpenSwoole is installed
- Verify port 8081 is not in use
- Check firewall settings

### 2. HTTP Trigger Issues
- Verify port 8082 is not in use
- Check if cURL is enabled in PHP
- Verify the trigger URL is accessible

### 3. Browser Notifications
- Ensure notification permission is granted
- Check browser console for errors
- Verify WebSocket connection is established

### 4. Database Issues
- Verify database connection
- Check table structure for notification_requests
- Ensure proper user permissions

## File Structure

```
websocket_Server/
├── notification_server.php      # WebSocket server
├── notification_trigger.php     # HTTP trigger server
├── notification_client.js       # JavaScript client
├── test_notifications.html     # Test page
├── README.md                   # This file
└── package.json               # Node.js dependencies (if needed)

push_notification.php           # Push notification service
```

## Notes

- The system is designed to work with your existing `fetchApprovalNotification` method
- No changes are required to your existing notification database structure
- The system is scalable and can handle multiple concurrent connections
- Browser notifications require HTTPS in production environments 