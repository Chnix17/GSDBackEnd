// Push Notification Manager
class PushNotificationManager {
    constructor() {
        // Try to load VAPID key from server or use the hardcoded one
        this.vapidPublicKey = 'BLvCu4r6PGPbVPfBFsXhJVUz8gSpeA-PL0J3To4Y55Tvr50xGMNesPIlyMwqjpvpvKHhEbxa1h-zjpxDd43HGkk';
        this.applicationServerKey = null; // Will be set in initialize
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.registration = null;
        this.subscription = null;
    }

    // Convert VAPID key to Uint8Array
    urlBase64ToUint8Array(base64String) {
        console.log('Converting VAPID key:', base64String);
        
        // Remove any whitespace
        base64String = base64String.trim();
        
        // Add padding if needed
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        console.log('Base64 with padding:', base64);
        
        try {
            const rawData = window.atob(base64);
            console.log('Raw data length:', rawData.length);
            
            // Handle different key formats
            let outputArray;
            
            if (rawData.length === 65) {
                // Standard uncompressed EC public key (65 bytes)
                outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                
                // Verify the first byte is 0x04 (uncompressed point format)
                if (outputArray[0] !== 0x04) {
                    console.warn('Warning: VAPID key first byte is not 0x04:', outputArray[0]);
                }
            } else if (rawData.length === 32) {
                // This might be a raw public key or compressed format
                // For VAPID, we typically need the full 65-byte uncompressed format
                console.error('VAPID key appears to be in compressed or raw format (32 bytes)');
                console.error('VAPID requires uncompressed EC public key (65 bytes)');
                console.error('Please generate a proper VAPID key pair using a tool like:');
                console.error('web-push generate-vapid-keys');
                throw new Error('VAPID key must be in uncompressed EC format (65 bytes). Current key is 32 bytes.');
            } else {
                throw new Error(`Unsupported VAPID key length: ${rawData.length} bytes (expected 65)`);
            }
            
            console.log('Converted to Uint8Array, length:', outputArray.length);
            console.log('First few bytes:', Array.from(outputArray.slice(0, 10)));
            
            return outputArray;
        } catch (error) {
            console.error('Error converting VAPID key:', error);
            throw new Error('Invalid VAPID key format: ' + error.message);
        }
    }

    // Initialize push notifications
    async initialize() {
        if (!this.isSupported) {
            console.log('Push notifications not supported');
            return false;
        }

        // Check if we're running on a supported protocol
        if (window.location.protocol === 'file:') {
            console.error('Service Workers require HTTPS or localhost. Cannot run on file:// protocol.');
            throw new Error('Service Workers require HTTPS or localhost. Please serve this page through a web server (e.g., http://localhost:8000) instead of opening it directly from the file system.');
        }

        // Check if we're on localhost or HTTPS
        if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && !window.location.hostname.includes('127.0.0.1')) {
            console.error('Service Workers require HTTPS or localhost for security reasons.');
            throw new Error('Service Workers require HTTPS or localhost. Please use a local development server or HTTPS.');
        }

        // Additional check for development environment
        console.log('Protocol:', window.location.protocol);
        console.log('Hostname:', window.location.hostname);
        console.log('Port:', window.location.port);

        try {
            // Convert VAPID key
            console.log('Converting VAPID public key...');
            this.applicationServerKey = this.urlBase64ToUint8Array(this.vapidPublicKey);
            console.log('VAPID key converted successfully');
            // Register service worker with proper path
            const swPath = './sw.js';
            console.log('Registering service worker at:', swPath);
            
            // Check if service worker is already registered
            const existingRegistration = await navigator.serviceWorker.getRegistration(swPath);
            if (existingRegistration) {
                console.log('Service Worker already registered:', existingRegistration);
                this.registration = existingRegistration;
            } else {
                this.registration = await navigator.serviceWorker.register(swPath);
                console.log('Service Worker registered:', this.registration);
            }

            // Wait for service worker to be ready
            console.log('Waiting for service worker to be ready...');
            await navigator.serviceWorker.ready;
            console.log('Service Worker is ready');
            
            // Check if service worker is active
            if (this.registration.active) {
                console.log('Service Worker is active');
            } else {
                console.log('Service Worker is not active yet, waiting...');
                
                // Try to skip waiting if there's a waiting service worker
                if (this.registration.waiting) {
                    console.log('Sending skip waiting message...');
                    this.registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                }
                
                // Wait for the service worker to become active with timeout
                await new Promise((resolve, reject) => {
                    const timeout = setTimeout(() => {
                        console.log('Service Worker activation timeout');
                        reject(new Error('Service Worker activation timeout'));
                    }, 10000); // 10 second timeout
                    
                    if (this.registration.active) {
                        clearTimeout(timeout);
                        resolve();
                    } else {
                        this.registration.addEventListener('updatefound', () => {
                            const newWorker = this.registration.installing;
                            if (newWorker) {
                                newWorker.addEventListener('statechange', () => {
                                    if (newWorker.state === 'activated') {
                                        console.log('Service Worker activated');
                                        clearTimeout(timeout);
                                        resolve();
                                    }
                                });
                            }
                        });
                        
                        // Also listen for the active service worker
                        if (this.registration.active) {
                            clearTimeout(timeout);
                            resolve();
                        }
                    }
                });
            }

            // Check if already subscribed
            this.subscription = await this.registration.pushManager.getSubscription();
            
            if (this.subscription) {
                console.log('Already subscribed to push notifications');
                return true;
            }

            return true;
        } catch (error) {
            console.error('Failed to initialize push notifications:', error);
            return false;
        }
    }

    // Request notification permission
    async requestPermission() {
        if (!this.isSupported) {
            throw new Error('Push notifications not supported');
        }

        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            console.log('Notification permission granted');
            return true;
        } else {
            console.log('Notification permission denied');
            return false;
        }
    }

    // Subscribe to push notifications
    async subscribe(userId) {
        if (!this.isSupported) {
            throw new Error('Push notifications not supported');
        }

        console.log('Starting subscription process...');
        console.log('Current registration:', this.registration);

        if (!this.registration) {
            console.log('No registration found, initializing...');
            const initialized = await this.initialize();
            if (!initialized) {
                throw new Error('Failed to initialize push notifications');
            }
        }
        
        // Double-check registration is available
        if (!this.registration) {
            throw new Error('Service worker registration is still not available');
        }
        
        console.log('Registration available:', this.registration);
        console.log('Registration active state:', this.registration.active);
        console.log('Registration installing state:', this.registration.installing);
        console.log('Registration waiting state:', this.registration.waiting);

        try {
            // Request permission first
            const permissionGranted = await this.requestPermission();
            if (!permissionGranted) {
                throw new Error('Notification permission denied');
            }

            // Ensure registration is available
            if (!this.registration || !this.registration.pushManager) {
                console.error('Registration details:', this.registration);
                throw new Error('Service worker registration failed');
            }

            console.log('Registration available, attempting to subscribe...');
            console.log('PushManager available:', !!this.registration.pushManager);
            console.log('Application server key length:', this.applicationServerKey.length);
            console.log('Application server key first byte:', this.applicationServerKey[0]);

            // Subscribe to push manager
            this.subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.applicationServerKey
            });

            console.log('Subscribed to push notifications:', this.subscription);

            // Send subscription to server
            await this.sendSubscriptionToServer(userId);

            return true;
        } catch (error) {
            console.error('Failed to subscribe to push notifications:', error);
            throw error;
        }
    }

    // Unsubscribe from push notifications
    async unsubscribe() {
        if (!this.subscription) {
            console.log('No subscription to unsubscribe from');
            return true;
        }

        try {
            await this.subscription.unsubscribe();
            this.subscription = null;
            console.log('Unsubscribed from push notifications');
            return true;
        } catch (error) {
            console.error('Failed to unsubscribe:', error);
            throw error;
        }
    }

    // Send subscription to server
    async sendSubscriptionToServer(userId) {
        if (!this.subscription) {
            throw new Error('No subscription to send');
        }

        const subscriptionData = {
            endpoint: this.subscription.endpoint,
            keys: {
                p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(this.subscription.getKey('p256dh')))),
                auth: btoa(String.fromCharCode.apply(null, new Uint8Array(this.subscription.getKey('auth'))))
            }
        };

        const requestData = {
            operation: 'save',
            user_id: userId,
            subscription: subscriptionData
        };

        console.log('Sending subscription data to server:', requestData);

        try {
            const response = await fetch('./save-push-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);

            const result = await response.json();
            console.log('Server response:', result);
            
            if (result.status === 'success') {
                console.log('Subscription saved to server');
                return true;
            } else {
                throw new Error(result.message || 'Failed to save subscription');
            }
        } catch (error) {
            console.error('Failed to send subscription to server:', error);
            throw error;
        }
    }

    // Check subscription status
    async getSubscriptionStatus() {
        if (!this.isSupported) {
            return { supported: false };
        }

        if (!this.registration) {
            return {
                supported: true,
                subscribed: false,
                permission: Notification.permission,
                registration: null
            };
        }

        const subscription = await this.registration.pushManager.getSubscription();
        const permission = Notification.permission;

        return {
            supported: true,
            subscribed: !!subscription,
            permission: permission,
            subscription: subscription,
            registration: this.registration
        };
    }

    // Test notification
    async testNotification() {
        if (!this.registration) {
            throw new Error('Service worker not registered');
        }

        try {
            console.log('Testing notification display...');
            console.log('Registration:', this.registration);
            console.log('Service Worker state:', this.registration.active ? 'active' : 'inactive');
            
            // Check if service worker is active
            if (!this.registration.active) {
                console.log('Service Worker is not active, waiting...');
                await navigator.serviceWorker.ready;
                console.log('Service Worker is now ready');
            }
            
            // Show notification through service worker
            const notificationOptions = {
                body: 'This is a test notification from GSD',
                icon: '/uploads/profileni.png',
                badge: '/uploads/profileni.png',
                tag: 'test-notification',
                requireInteraction: true,
                actions: [
                    {
                        action: 'view',
                        title: 'View',
                        icon: '/uploads/profileni.png'
                    },
                    {
                        action: 'dismiss',
                        title: 'Dismiss',
                        icon: '/uploads/profileni.png'
                    }
                ],
                data: {
                    url: window.location.href,
                    timestamp: Date.now()
                }
            };
            
            console.log('Showing notification with options:', notificationOptions);
            
            // Try to show notification directly first (for debugging)
            if ('Notification' in window && Notification.permission === 'granted') {
                console.log('Attempting to show notification directly...');
                const directNotification = new Notification('Approval Notification', {
                    body: 'A New Approval Request',
                    icon: '/uploads/profileni.png',
                    requireInteraction: true
                });
                
                // Auto-close after 5 seconds for direct notification
                setTimeout(() => {
                    directNotification.close();
                }, 5000);
            }
            
            // Show notification through service worker
            const result = await this.registration.showNotification('Test Notification (Service Worker)', notificationOptions);
            console.log('Notification show result:', result);
            
            return true;
        } catch (error) {
            console.error('Failed to show test notification:', error);
            console.error('Error details:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
            throw error;
        }
    }

    // Test simple notification (bypasses service worker)
    async testSimpleNotification() {
        try {
            console.log('Testing simple notification...');
            
            // Check if notifications are supported
            if (!('Notification' in window)) {
                throw new Error('Notifications not supported in this browser');
            }
            
            // Check permission
            if (Notification.permission !== 'granted') {
                throw new Error('Notification permission not granted. Current permission: ' + Notification.permission);
            }
            
            console.log('Notification permission is granted');
            
            // Show a simple notification
            const notification = new Notification('Simple Test Notification', {
                body: 'This is a simple test notification',
                icon: '/uploads/profileni.png',
                requireInteraction: true,
                tag: 'simple-test'
            });
            
            console.log('Simple notification created:', notification);
            
            // Add event listeners for debugging
            notification.onclick = () => {
                console.log('Simple notification clicked');
                notification.close();
            };
            
            notification.onshow = () => {
                console.log('Simple notification shown');
            };
            
            notification.onerror = (error) => {
                console.error('Simple notification error:', error);
            };
            
            notification.onclose = () => {
                console.log('Simple notification closed');
            };
            
            // Auto-close after 10 seconds
            setTimeout(() => {
                console.log('Auto-closing simple notification');
                notification.close();
            }, 10000);
            
            return true;
        } catch (error) {
            console.error('Failed to show simple notification:', error);
            throw error;
        }
    }
}

// Global instance
window.pushNotificationManager = new PushNotificationManager();

// Helper function to show server setup instructions
function showServerSetupInstructions() {
    const instructions = `
        To run this application locally, you need to serve it through a web server.
        
        Option 1 - Using PHP built-in server:
        Open terminal/command prompt in this directory and run:
        php -S localhost:8000
        
        Option 2 - Using Python:
        python -m http.server 8000
        
        Option 3 - Using Node.js (if you have http-server installed):
        npx http-server -p 8000
        
        Then open: http://localhost:8000/test-push-notifications.html
    `;
    
    console.log(instructions);
    
    // If there's a status element, update it
    const statusElement = document.getElementById('status');
    if (statusElement) {
        statusElement.innerHTML = `
            <strong>Setup Required:</strong><br>
            This application must be served through a web server.<br>
            <strong>Quick setup:</strong> Run <code>php -S localhost:8000</code> in this directory,<br>
            then open <a href="http://localhost:8000/test-push-notifications.html" target="_blank">http://localhost:8000/test-push-notifications.html</a>
        `;
        statusElement.className = 'status warning';
    }
}

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', async () => {
    try {
        await window.pushNotificationManager.initialize();
        console.log('Push notification manager initialized');
    } catch (error) {
        console.error('Failed to initialize push notification manager:', error);
        
        // Show setup instructions if it's a protocol error
        if (error.message.includes('file://') || error.message.includes('HTTPS') || error.message.includes('localhost')) {
            showServerSetupInstructions();
        }
    }
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PushNotificationManager;
} 