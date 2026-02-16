<?php

echo "=== Server Configuration Check ===\n\n";

// Check if headers are being received
$headers = getallheaders();
echo "1. Headers Received:\n";
foreach ($headers as $name => $value) {
    echo "   $name: $value\n";
}

echo "\n2. PHP Info:\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "   memory_limit: " . ini_get('memory_limit') . "\n";
echo "   register_globals: " . ini_get('register_globals') . "\n";

echo "\n3. Apache Modules:\n";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "   mod_rewrite: " . (in_array('mod_rewrite', $modules) ? 'Yes' : 'No') . "\n";
    echo "   mod_headers: " . (in_array('mod_headers', $modules) ? 'Yes' : 'No') . "\n";
}

echo "\n4. Laravel Environment:\n";
echo "   APP_ENV: " . (env('APP_ENV', 'local')) . "\n";
echo "   APP_DEBUG: " . (env('APP_DEBUG', 'false')) . "\n";

echo "\n=== End Check ===\n";
