<?php

// ERPLY Debug Endpoint
require __DIR__.'/vendor/autoload.php';

echo "=== ERPLY API Debug ===\n\n";

// Load configuration
$env = [
    'ERPLY_API_URL' => env('ERPLY_API_URL', 'https://606950.erply.com/api/'),
    'ERPLY_USERNAME' => env('ERPLY_USERNAME', 'support@retailcare.com.au'),
    'ERPLY_PASSWORD' => env('ERPLY_PASSWORD', 'NF7c8XUFv0!C'),
    'ERPLY_CLIENT_CODE' => env('ERPLY_CLIENT_CODE', '606950'),
];

echo "Configuration:\n";
foreach ($env as $key => $value) {
    echo "$key: " . ($key === 'ERPLY_PASSWORD' ? str_repeat('*', strlen($value)) : $value) . "\n";
}
echo "\n";

// Test 1: Authentication
echo "=== Test 1: Authentication ===\n";
try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $env['ERPLY_API_URL'] . 'auth/login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'username' => $env['ERPLY_USERNAME'],
        'password' => $env['ERPLY_PASSWORD'],
        'clientCode' => $env['ERPLY_CLIENT_CODE']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    echo "Response: $response\n\n";
    
    $data = json_decode($response, true);
    $sessionToken = $data['session'] ?? $data['session_token'] ?? $data['token'] ?? null;
    
    if ($sessionToken) {
        echo "✅ Authentication successful! Session token: " . substr($sessionToken, 0, 10) . "...\n\n";
        
        // Test 2: Get Customers
        echo "=== Test 2: Get Customers ===\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $env['ERPLY_API_URL'] . 'customers');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'request' => json_encode([
                'getCustomers' => [
                    'page' => 1,
                    'limit' => 10
                ]
            ])
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $sessionToken,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP Status: $httpCode\n";
        echo "Response: $response\n\n";
        
        $customerData = json_decode($response, true);
        $customers = $customerData['data'] ?? $customerData['customers'] ?? [];
        
        echo "Customers found: " . count($customers) . "\n";
        
        if (!empty($customers)) {
            echo "\nFirst customer:\n";
            print_r($customers[0]);
        }
        
    } else {
        echo "❌ Authentication failed!\n";
        echo "Response data: " . json_encode($data) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
