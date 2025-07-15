<?php
require_once './vendor/autoload.php';

use Minishlink\WebPush\VAPID;

// Completely disable OpenSSL config to avoid file issues
putenv('OPENSSL_CONF=');
putenv('OPENSSL_CONF_PATH=');

try {
    // Generate VAPID keys
    $keys = VAPID::createVapidKeys();
    
    echo "VAPID Keys Generated:\n";
    echo "Public Key: " . $keys['publicKey'] . "\n";
    echo "Private Key: " . $keys['privateKey'] . "\n";
    
    // Test creating PEM format
    $privateKey = openssl_pkey_new([
        'private_key_bits' => 256,
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1'
    ]);
    
    if ($privateKey) {
        openssl_pkey_export($privateKey, $privateKeyPEM);
        $publicKey = openssl_pkey_get_details($privateKey)['key'];
        
        echo "\nPEM Format Keys:\n";
        echo "Private Key PEM:\n" . $privateKeyPEM . "\n";
        echo "Public Key PEM:\n" . $publicKey . "\n";
        
        openssl_pkey_free($privateKey);
    } else {
        echo "\nFailed to generate PEM keys\n";
        while ($error = openssl_error_string()) {
            echo "Error: $error\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 