<?php

// Debug ERPLY Database and API
echo "=== ERPLY Database & API Debug ===\n\n";

// Load Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

// Check database connection
try {
    $pdo = DB::connection()->getPdo();
    echo "✅ Database Connection: SUCCESS\n";
    echo "Database: " . DB::connection()->getDatabaseName() . "\n";
} catch (Exception $e) {
    echo "❌ Database Connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

// Check if erply_tokens table exists
try {
    $tables = DB::select('SHOW TABLES');
    $hasErplyTokens = in_array('erply_tokens', array_column($tables, 'Tables_in_database'));
    
    if ($hasErplyTokens) {
        echo "✅ erply_tokens table: EXISTS\n";
        
        // Check existing tokens
        $tokens = DB::table('erply_tokens')->get();
        echo "Existing tokens: " . count($tokens) . "\n";
        
        foreach ($tokens as $token) {
            echo "- ID: {$token->id}, User: {$token->username}, Expires: {$token->expires_at}\n";
        }
    } else {
        echo "❌ erply_tokens table: MISSING\n";
    }
} catch (Exception $e) {
    echo "❌ Table check failed: " . $e->getMessage() . "\n";
}

// Test ERPLY API directly
echo "\n=== Testing ERPLY API Directly ===\n";

$apiUrl = env('ERPLY_API_URL', 'https://api.erply.com/api/');
$username = env('ERPLY_USERNAME', 'support@retailcare.com.au');
$password = env('ERPLY_PASSWORD', 'NF7c8XUFv0!C');
$clientCode = env('ERPLY_CLIENT_CODE', '606950');

echo "API URL: $apiUrl\n";
echo "Username: $username\n";
echo "Client Code: $clientCode\n";

// Test authentication
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
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "...\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    
    if (isset($data->session)) {
        echo "✅ Authentication SUCCESS\n";
        echo "Session Key: " . substr($data->session, 0, 10) . "...\n";
        
        // Test getCustomers
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $apiUrl . 'customers');
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
            'session' => $data->session,
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
        curl_close($ch2);
        
        echo "Customers HTTP Status: $httpCode2\n";
        echo "Customers Response: " . substr($response2, 0, 500) . "...\n\n";
        
        if ($httpCode2 == 200) {
            $data2 = json_decode($response2, true);
            $customerCount = isset($data2->status->recordsTotal) ? $data2->status->recordsTotal : 0;
            echo "Total Customers in ERPLY: $customerCount\n";
            
            if ($customerCount > 0) {
                echo "✅ ERPLY account HAS customers\n";
            } else {
                echo "❌ ERPLY account is EMPTY\n";
            }
        }
    } else {
        echo "❌ Authentication FAILED\n";
    }
} else {
    echo "❌ HTTP Request failed\n";
}

echo "\n=== Debug Complete ===\n";
?>
