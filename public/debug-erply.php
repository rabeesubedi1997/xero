<?php

// Debug ERPLY Database and API
echo "=== ERPLY Database & API Debug ===\n\n";

// Load Laravel environment manually
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    echo "✅ .env file found at: $dotenvPath\n";
    
    // Load environment variables manually
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            putenv(trim($line));
        }
    }
    
    echo "Environment variables loaded manually\n";
} else {
    echo "❌ .env file NOT found at: $dotenvPath\n";
}

// Get environment variables
$apiUrl = getenv('ERPLY_API_URL') ?: 'https://api.erply.com/api/';
$username = getenv('ERPLY_USERNAME') ?: 'support@retailcare.com.au';
$password = getenv('ERPLY_PASSWORD') ?: 'NF7c8XUFv0!C';
$clientCode = getenv('ERPLY_CLIENT_CODE') ?: '606950';

echo "API URL: $apiUrl\n";
echo "Username: $username\n";
echo "Client Code: $clientCode\n";

// Check database connection without Laravel
try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $database = getenv('DB_DATABASE') ?: 'stagingsync_xero';
    $username_db = getenv('DB_USERNAME') ?: 'root';
    $password_db = getenv('DB_PASSWORD') ?: '';
    
    echo "Attempting database connection...\n";
    echo "Host: $host\n";
    echo "Database: $database\n";
    echo "Username: $username_db\n";
    
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username_db, $password_db);
    
    if ($pdo) {
        echo "✅ Database Connection: SUCCESS\n";
        
        // Check if erply_tokens table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'erply_tokens'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $hasErplyTokens = !empty($tables);
        
        if ($hasErplyTokens) {
            echo "✅ erply_tokens table: EXISTS\n";
            
            // Check existing tokens
            $stmt = $pdo->prepare("SELECT * FROM erply_tokens WHERE username = ? AND client_code = ?");
            $stmt->execute([$username, $clientCode]);
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Existing tokens: " . count($tokens) . "\n";
            
            foreach ($tokens as $token) {
                echo "- ID: {$token['id']}, User: {$token['username']}, Expires: {$token['expires_at']}\n";
            }
        } else {
            echo "❌ erply_tokens table: MISSING\n";
            
            // Try to create the table
            echo "Attempting to create erply_tokens table...\n";
            $createTableSQL = "
                CREATE TABLE erply_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_code VARCHAR(255) NOT NULL,
                    username VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    session_key VARCHAR(255) NOT NULL,
                    jwt_token TEXT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    last_used_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ";
            
            if ($pdo->exec($createTableSQL)) {
                echo "✅ erply_tokens table: CREATED\n";
            } else {
                echo "❌ erply_tokens table: CREATION FAILED\n";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Database Connection: FAILED - " . $e->getMessage() . "\n";
}

// Test ERPLY API directly
echo "\n=== Testing ERPLY API Directly ===\n";

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
                echo "Sample customer data:\n";
                print_r(array_slice($data2->records ?? [], 0, 2));
            } else {
                echo "❌ ERPLY account is EMPTY\n";
                echo "Full response structure:\n";
                print_r($data2);
            }
        }
    } else {
        echo "❌ Authentication FAILED\n";
        echo "Full response:\n";
        print_r($data);
    }
} else {
    echo "❌ HTTP Request failed\n";
}

echo "\n=== Debug Complete ===\n";
?>
