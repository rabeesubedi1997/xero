<?php
require_once 'config.php';
require_once 'ErplyAPI.php';

// Initialize API and database connection
$api = new ErplyAPI();
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$api->verifyConnection()) {
    die("Failed to connect to Erply API. Please check your credentials in config.php");
}

// Test a basic product request
try {
    $testResponse = $api->sendRequest("getProducts", [
        'pageNo' => 1,
        'recordsOnPage' => 1
    ]);
    $testData = json_decode($testResponse, true);
    var_dump($testData); // This will show the raw API response
} catch (Exception $e) {
    die("API Test failed: " . $e->getMessage() . "\n");
}

// Set time limit for long-running script
set_time_limit(0);

// Function to sync a batch of products and return results
function syncProductsBatch($products, $db, $api)
{
    $results = [
        'success' => 0,
        'errors' => 0,
        'error_messages' => [],
        'processed_ids' => []
    ];

    foreach ($products as $product) {
        try {
            if (!isset($product['productID'])) {
                throw new Exception("Missing productID in product data");
            }

            $productId = (int)$product['productID'];
            $name = $db->real_escape_string($product['name'] ?? 'Unknown');
            $code = $db->real_escape_string($product['code'] ?? '');
            $price = (float)($product['price'] ?? 0);
            $description = $db->real_escape_string($product['description'] ?? '');
            $type = $product['type'] ?? 'PRODUCT';

            // Insert/update main product table
            $stmt = $db->prepare("
                INSERT INTO from_erply_products 
                (product_id, name, code, price, description, type) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                code = VALUES(code), 
                price = VALUES(price),
                description = VALUES(description),
                type = VALUES(type)
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $db->error);
            }

            $stmt->bind_param("issdss", $productId, $name, $code, $price, $description, $type);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            // If matrix product, update matrix table too
            if ($type === 'MATRIX') {
                $parentId = (int)($product['parentProductID'] ?? 0);
                $stmt = $db->prepare("
                    INSERT INTO from_erply_matrix_products 
                    (product_id, parent_id, name, code, price, description) 
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    parent_id = VALUES(parent_id),
                    name = VALUES(name), 
                    code = VALUES(code), 
                    price = VALUES(price),
                    description = VALUES(description)
                ");

                if (!$stmt) {
                    throw new Exception("Matrix prepare failed: " . $db->error);
                }

                $stmt->bind_param("iissds", $productId, $parentId, $name, $code, $price, $description);
                if (!$stmt->execute()) {
                    throw new Exception("Matrix execute failed: " . $stmt->error);
                }
            }

            // Sync stock quantity
            $stockResult = syncProductStock($productId, $db, $api);

            $results['success']++;
            $results['processed_ids'][] = $productId;
            echo "Synced product #$productId: $name (Stock: $stockResult)\n";
        } catch (Exception $e) {
            $results['errors']++;
            $errorMsg = "Product #$productId: " . $e->getMessage();
            $results['error_messages'][] = $errorMsg;
            echo "ERROR: $errorMsg\n";
            continue;
        }
    }

    return $results;
}

// Function to sync product stock
function syncProductStock($productId, $db, $api)
{
    $stockData = $api->getProductStock($productId);

    if (empty($stockData['records'])) {
        throw new Exception("No stock data available");
    }

    // Calculate total available stock across all warehouses
    $totalAvailable = 0;
    foreach ($stockData['records'] as $stock) {
        if (!isset($stock['amountInStock']) || !isset($stock['amountReserved'])) {
            continue;
        }
        $totalAvailable += ((int)$stock['amountInStock'] - (int)$stock['amountReserved']);
    }

    // Update quantity in both tables
    $update1 = $db->query("UPDATE from_erply_products SET quantity = $totalAvailable WHERE product_id = $productId");
    $update2 = $db->query("UPDATE from_erply_matrix_products SET quantity = $totalAvailable WHERE product_id = $productId");

    if (!$update1 || !$update2) {
        throw new Exception("Failed to update stock quantities");
    }

    return $totalAvailable;
}

// Main sync process
try {
    $page = 1;
    $pageSize = 100; // Erply's max is 100 records per page
    $totalSynced = 0;
    $totalErrors = 0;

    echo "Starting Erply product sync...\n\n";

    do {
        echo "Fetching page $page...\n";
        $response = $api->getProducts($page, $pageSize);
        var_dump($response);

        if (empty($response['records'])) {
            echo "No more products found.\n";
            break;
        }

        $products = $response['records'];
        $batchResult = syncProductsBatch($products, $db, $api);
        $totalSynced += $batchResult['success'];
        $totalErrors += $batchResult['errors'];

        echo "Page $page results: " . $batchResult['success'] . " succeeded, " . $batchResult['errors'] . " failed\n\n";

        $page++;
        sleep(1); // Add 1 second delay between requests

        // Sleep for a short time to avoid hitting API rate limits
        usleep(500000); // 0.5 seconds

    } while (!empty($response['records']));

    echo "\nSYNC COMPLETED\n";
    echo "Total products synced successfully: $totalSynced\n";
    echo "Total errors: $totalErrors\n";

    if ($totalErrors > 0) {
        echo "\nConsider checking the error messages above for troubleshooting.\n";
    }
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
}

$db->close();
