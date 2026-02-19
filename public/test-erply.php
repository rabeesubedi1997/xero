<?php

// Test ERPLY API directly based on documentation
echo "=== ERPLY API Test (Based on Documentation) ===\n\n";

// Load Laravel environment properly
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

// Get environment variables from Laravel
$apiUrl = env('ERPLY_API_URL', 'https://api.erply.com/api/');
$username = env('ERPLY_USERNAME', 'support@retailcare.com.au');
$password = env('ERPLY_PASSWORD', 'NF7c8XUFv0!C');
$clientCode = env('ERPLY_CLIENT_CODE', '606950');

echo "API URL: $apiUrl\n";
echo "Username: $username\n";
echo "Client Code: $clientCode\n\n";

// Test authentication using ERPLY format
echo "=== Authentication Test ===\n";

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
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "...\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    
    // Check for session key in response
    $sessionKey = $data['session'] ?? $data['session_key'] ?? $data['sessionKey'] ?? null;
    
    if ($sessionKey) {
        echo "✅ SUCCESS! Session key: " . substr($sessionKey, 0, 10) . "...\n\n";
        
        // Test customers using ERPLY format
        echo "=== Customers Test (ERPLY Format) ===\n";
        
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
            
            // Check for records array (ERPLY format)
            $customers = $customerData['records'] ?? $customerData['data'] ?? $customerData['customers'] ?? [];
            
            echo "Customers found: " . count($customers) . "\n";
            
            if (!empty($customers)) {
                echo "First customer:\n";
                print_r($customers[0]);
            } else {
                echo "No customers found in response\n";
                echo "Full response structure:\n";
                print_r($customerData);
                
                // Try to get total customers count
                echo "\n=== Testing Customer Count ===\n";
                
                $ch3 = curl_init();
                curl_setopt($ch3, CURLOPT_URL, $apiUrl . 'customers');
                curl_setopt($ch3, CURLOPT_POST, true);
                curl_setopt($ch3, CURLOPT_POSTFIELDS, http_build_query([
                    'session' => $sessionKey,
                    'request' => json_encode([
                        'getCustomers' => [
                            'page' => 1,
                            'limit' => 1
                        ]
                    ])
                ]));
                curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch3, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch3, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ]);
                
                $response3 = curl_exec($ch3);
                $httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
                curl_close($ch3);
                
                echo "Customer Count Test - HTTP Status: $httpCode3\n";
                echo "Customer Count Test - Response: " . substr($response3, 0, 500) . "...\n\n";
                
                $countData = json_decode($response3, true);
                echo "Customer Count Test - Full Response:\n";
                print_r($countData);
            }
        } else {
            echo "❌ Failed to get customers (HTTP $httpCode2)\n";
        }
    } else {
        echo "❌ Authentication failed\n";
        echo "Full response: " . $response . "\n\n";
        echo "Response structure:\n";
        print_r($data);
    }
} else {
    echo "❌ Authentication failed (HTTP $httpCode)\n";
}

echo "\n=== Test Complete ===\n";
?>
