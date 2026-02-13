<?php

// Simple debug endpoint to test tenant detection
require __DIR__.'/vendor/autoload.php';

use Illuminate\Http\Request;

echo "=== Xero API Debug ===\n\n";

// Check database tokens
try {
    $tokens = \App\Models\XeroToken::all();
    echo "Database tokens found: " . $tokens->count() . "\n";
    
    if ($tokens->isNotEmpty()) {
        echo "Available tenant IDs:\n";
        foreach ($tokens as $token) {
            echo "- " . $token->tenant_id . " (" . $token->tenant_name . ")\n";
        }
    } else {
        echo "No tokens found in database\n";
    }
    
    echo "\n=== Request Headers Test ===\n";
    $headers = getallheaders();
    echo "All headers:\n";
    foreach ($headers as $name => $value) {
        echo "$name: $value\n";
    }
    
    echo "\n=== Xero-Tenant-ID Header Test ===\n";
    $xeroTenantId = $_SERVER['HTTP_XERO_TENANT_ID'] ?? $_SERVER['HTTP_XERO_TENANT_ID'] ?? $_SERVER['HTTP_XERO_TENANT_ID'] ?? null;
    echo "Xero-Tenant-ID header: " . ($xeroTenantId ?? 'NULL') . "\n";
    
    echo "\n=== Xero-Tenant-ID URL Parameter Test ===\n";
    $urlParam = $_GET['Xero-Tenant-ID'] ?? $_GET['xero-tenant-id'] ?? $_GET['xero-tenant-id'] ?? null;
    echo "Xero-Tenant-ID URL param: " . ($urlParam ?? 'NULL') . "\n";
    
    echo "\n=== Environment ===\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Laravel Version: " . app()->version() . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
