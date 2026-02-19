<?php

// Simple ERPLY API Test
require __DIR__.'/vendor/autoload.php';

echo "=== ERPLY Direct API Test ===\n\n";

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(base_path());
$dotenv->load();

$apiUrl = $_ENV['ERPLY_API_URL'] ?? 'https://api.erply.com/api/';
$username = $_ENV['ERPLY_USERNAME'] ?? 'support@retailcare.com.au';
$password = $_ENV['ERPLY_PASSWORD'] ?? 'NF7c8XUFv0!C';
$clientCode = $_ENV['ERPLY_CLIENT_CODE'] ?? '606950';

echo "API URL: $apiUrl\n";
echo "Username: $username\n";
echo "Client Code: $clientCode\n\n";

// Test 1: Direct authentication
echo "=== Test 1: Direct Authentication ===\n";

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
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Final URL: $finalUrl\n";
echo "Response: " . substr($response, 0, 500) . "...\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    
    // Check for session key in response
    $sessionKey = $data['session'] ?? $data['session_key'] ?? $data['sessionKey'] ?? null;
    
    if ($sessionKey) {
        echo "✅ SUCCESS! Session key found: " . substr($sessionKey, 0, 10) . "...\n\n";
        
        // Test 2: Get customers with session key
        echo "=== Test 2: Get Customers ===\n";
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $apiUrl . 'customers');
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
            'session' => $sessionKey,
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
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        
        echo "HTTP Status: $httpCode2\n";
        echo "Response: " . substr($response2, 0, 500) . "...\n\n";
        
        if ($httpCode2 == 200) {
            $customerData = json_decode($response2, true);
            $customers = $customerData['data'] ?? $customerData['customers'] ?? [];
            
            echo "Customers found: " . count($customers) . "\n";
            
            if (!empty($customers)) {
                echo "First customer:\n";
                print_r($customers[0]);
            }
        } else {
            echo "❌ Failed to get customers\n";
        }
    } else {
        echo "❌ Authentication failed\n";
        echo "Full response: " . $response . "\n\n";
    }
    
} else {
    echo "❌ Authentication failed\n";
}

echo "\n=== Test Complete ===\n";
?>
