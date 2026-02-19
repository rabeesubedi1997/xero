<?php
require_once 'config.php';
require_once 'ErplyAPI.php';

// Initialize API
$api = new ErplyAPI();

// Verify connection
if (!$api->verifyConnection()) {
    die("Failed to connect to Erply API. Please check your credentials.");
}

/**
 * Sync product quantity from Erply to local database
 */
function syncQuantity($productid, $connect, $api)
{
    try {
        // Get stock info from Erply
        $result = $api->sendRequest("getProductStock", [
            'productID' => $productid,
            'getAmountReserved' => 1,
            'warehouseID' => 1 // Change if you use specific warehouses
        ]);

        $data = json_decode($result, true);

        if (empty($data['records'][0])) {
            logSync("Quantity sync failed for product $productid - No data returned", 'error');
            return false;
        }

        $stock = $data['records'][0];
        $available = (int)$stock['amountInStock'] - (int)$stock['amountReserved'];

        // Update both product tables
        $update1 = $connect->prepare("UPDATE from_erply_products SET quantity = ? WHERE product_id = ?");
        $update1->bind_param("is", $available, $productid);
        $update1->execute();

        $update2 = $connect->prepare("UPDATE from_erply_matrix_products SET quantity = ? WHERE product_id = ?");
        $update2->bind_param("is", $available, $productid);
        $update2->execute();

        logSync("Synced quantity for product $productid - Available: $available", 'success');
        return true;
    } catch (Exception $e) {
        logSync("Quantity sync failed for product $productid - " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Fetch products from Erply with pagination
 */
function fetchErplyProducts($api, $page = 1, $pageSize = 100)
{
    try {
        $response = $api->sendRequest("getProducts", [
            'pageNo' => $page,
            'recordsOnPage' => $pageSize,
            'orderByDir' => 'asc',
            'getMatrixVariations' => 1,
            'getImages' => 0
        ]);

        return json_decode($response, true);
    } catch (Exception $e) {
        logSync("Failed to fetch products - " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Log sync activities
 */
function logSync($message, $status = 'info')
{
    global $connect;

    $stmt = $connect->prepare("INSERT INTO erply_sync_log (sync_type, status, message) VALUES (?, ?, ?)");
    $syncType = debug_backtrace()[1]['function'] ?? 'manual';
    $stmt->bind_param("sss", $syncType, $status, $message);
    $stmt->execute();
}

/**
 * Main sync process
 */
function runFullSync($connect, $api)
{
    logSync("Starting full product sync", 'start');

    $page = 1;
    $totalProcessed = 0;
    $hasMore = true;

    while ($hasMore) {
        $products = fetchErplyProducts($api, $page, 100);

        if (empty($products['records'])) {
            $hasMore = false;
            continue;
        }

        foreach ($products['records'] as $product) {
            // Skip products without ID
            if (empty($product['productID'])) continue;

            $productId = $product['productID'];
            $isMatrix = ($product['type'] === 'MATRIX');

            try {
                // Prepare product data
                $name = $connect->real_escape_string($product['name'] ?? '');
                $code = $connect->real_escape_string($product['code'] ?? '');
                $price = $product['price'] ?? 0;

                // Insert/update main product
                $stmt = $connect->prepare("
                    INSERT INTO from_erply_products 
                    (product_id, name, code, price) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    code = VALUES(code), 
                    price = VALUES(price)
                ");
                $stmt->bind_param("issd", $productId, $name, $code, $price);
                $stmt->execute();

                // If matrix product, update matrix table too
                if ($isMatrix) {
                    $stmt = $connect->prepare("
                        INSERT INTO from_erply_matrix_products 
                        (product_id, name, code, price) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        name = VALUES(name), 
                        code = VALUES(code), 
                        price = VALUES(price)
                    ");
                    $stmt->bind_param("issd", $productId, $name, $code, $price);
                    $stmt->execute();
                }

                // Sync quantity
                syncQuantity($productId, $connect, $api);

                $totalProcessed++;
            } catch (Exception $e) {
                logSync("Failed to process product $productId - " . $e->getMessage(), 'error');
            }
        }

        $page++;

        // Safety check to prevent infinite loops
        if ($page > 50) {
            logSync("Safety break after 50 pages", 'warning');
            break;
        }
    }

    logSync("Completed full product sync. Processed $totalProcessed products.", 'complete');
    return $totalProcessed;
}

// Execute full sync
header('Content-Type: text/plain');
echo "Starting Erply Product Sync...\n";
$start = microtime(true);

$processed = runFullSync($connect, $api);

$time = round(microtime(true) - $start, 2);
echo "\nSync completed in {$time}s. Processed $processed products.\n";

// Close connection
$connect->close();
