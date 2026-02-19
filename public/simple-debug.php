<?php

echo "=== Simple ERPLY Debug ===\n\n";

// Load .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    echo "✅ .env file found\n";
    
    // Read and parse .env
    $envContent = file_get_contents($envFile);
    $lines = explode("\n", $envContent);
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
    
    echo "✅ Environment loaded\n";
} else {
    echo "❌ .env file not found\n";
}

// Get ERPLY credentials
$apiUrl = getenv('ERPLY_API_URL') ?: 'https://api.erply.com/api/';
$username = getenv('ERPLY_USERNAME') ?: 'support@retailcare.com.au';
$password = getenv('ERPLY_PASSWORD') ?: 'NF7c8XUFv0!C';
$clientCode = getenv('ERPLY_CLIENT_CODE') ?: '606950';

echo "API URL: $apiUrl\n";
echo "Username: $username\n";
echo "Client Code: $clientCode\n\n";

// Test ERPLY API
echo "=== Testing ERPLY API ===\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl . 'login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => $username,
    'password' => $password,
    'clientCode' => $clientCode
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "CURL Error: $error\n";
echo "Response: " . substr($response, 0, 1000) . "\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    
    if (isset($data['session'])) {
        echo "✅ Authentication SUCCESS\n";
        echo "Session Key: " . substr($data['session'], 0, 10) . "...\n\n";
        
        // Test getCustomers
        echo "=== Testing getCustomers ===\n";
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $apiUrl . 'customers');
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
            'session' => $data['session'],
            'request' => json_encode([
                'getCustomers' => [
                    'page' => 1,
                    'limit' => 5
                ]
            ])
        ]));
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        
        $response2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $error2 = curl_error($ch2);
        curl_close($ch2);
        
        echo "Customers HTTP Status: $httpCode2\n";
        echo "Customers CURL Error: $error2\n";
        echo "Customers Response: " . substr($response2, 0, 1000) . "\n\n";
        
        if ($httpCode2 == 200) {
            $data2 = json_decode($response2, true);
            $customerCount = isset($data2['status']['recordsTotal']) ? $data2['status']['recordsTotal'] : 0;
            echo "Total Customers in ERPLY: $customerCount\n";
            
            if ($customerCount > 0) {
                echo "✅ ERPLY account HAS customers\n";
            } else {
                echo "❌ ERPLY account is EMPTY\n";
            }
        }
    } else {
        echo "❌ Authentication FAILED\n";
        echo "Full response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ HTTP Request failed\n";
}

echo "\n=== Debug Complete ===\n";
?>
