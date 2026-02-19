<?php
require_once 'config.php';
require_once 'ErplyAPI.php';

if (empty($_GET['product_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Please provide product_id parameter']));
}

$productId = (int)$_GET['product_id'];
$api = new ErplyAPI();
$connect = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

header('Content-Type: application/json');

try {
    // DEBUG: Verify API connection first
    if (!$api->verifyConnection()) {
        throw new Exception("Failed to authenticate with Erply API");
    }

    // DEBUG: Log the request we're about to make
    error_log("Attempting to fetch product ID: $productId");

    // Get product details with more comprehensive parameters
    $response = $api->sendRequest("getProducts", [
        'productID' => $productId,
        'getMatrixVariations' => 1,
        'getImages' => 0,
        'getFiles' => 0,
        'getRelatedProducts' => 0,
        'getStockInfo' => 1,
        'warehouseID' => 1, // Important if you use warehouse-specific products
        'lang' => 'eng' // Change if using different language
    ]);

    // DEBUG: Log raw response
    error_log("Raw API response: " . $response);

    $productData = json_decode($response, true);

    // DEBUG: Log decoded response
    error_log("Decoded response: " . print_r($productData, true));

    if (empty($productData['records'])) {
        throw new Exception("Product not found in Erply. Response: " . json_encode($productData));
    }

    if (empty($productData['records'][0])) {
        throw new Exception("Product data empty in response");
    }

    $product = $productData['records'][0];
    $isMatrix = ($product['type'] === 'MATRIX');

    // Insert/update product with more fields
    $name = $connect->real_escape_string($product['name'] ?? '');
    $code = $connect->real_escape_string($product['code'] ?? '');
    $price = $product['price'] ?? 0;
    $description = $connect->real_escape_string($product['description'] ?? '');

    $stmt = $connect->prepare("
        INSERT INTO from_erply_products 
        (product_id, name, code, price, description) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        name = VALUES(name), 
        code = VALUES(code), 
        price = VALUES(price),
        description = VALUES(description)
    ");
    $stmt->bind_param("issds", $productId, $name, $code, $price, $description);
    $stmt->execute();

    if ($isMatrix) {
        $stmt = $connect->prepare("
            INSERT INTO from_erply_matrix_products 
            (product_id, name, code, price, description) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            name = VALUES(name), 
            code = VALUES(code), 
            price = VALUES(price),
            description = VALUES(description)
        ");
        $stmt->bind_param("issds", $productId, $name, $code, $price, $description);
        $stmt->execute();
    }

    // Sync quantity with more detailed logging
    $quantityResult = syncQuantity($productId, $connect, $api);

    // Get updated record with more fields
    $result = $connect->query("
        SELECT product_id, name, code, price, quantity, description 
        FROM from_erply_products 
        WHERE product_id = $productId
    ");
    $product = $result->fetch_assoc();

    echo json_encode([
        'status' => 'success',
        'product' => $product,
        'api_response' => $productData, // Include API response for debugging
        'quantity_sync' => $quantityResult
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'product_id' => $productId,
        'debug' => [
            'api_credentials' => [
                'clientCode' => ERPLY_CLIENT_CODE,
                'username' => ERPLY_USERNAME,
                'api_url' => ERPLY_API_URL
            ]
        ]
    ], JSON_PRETTY_PRINT);
}

$connect->close();

function syncQuantity($productid, $connect, $api)
{
    try {
        // Get stock info from all warehouses
        $result = $api->sendRequest("getProductStock", [
            'productID' => $productid,
            'getAmountReserved' => 1,
            'warehouseID' => 0 // 0 = all warehouses
        ]);

        $data = json_decode($result, true);

        if (empty($data['records'])) {
            throw new Exception("No stock records found for product");
        }

        // Calculate total available across all warehouses
        $totalAvailable = 0;
        foreach ($data['records'] as $stock) {
            $totalAvailable += ((int)$stock['amountInStock'] - (int)$stock['amountReserved']);
        }

        // Update both product tables
        $update1 = $connect->prepare("UPDATE from_erply_products SET quantity = ? WHERE product_id = ?");
        $update1->bind_param("is", $totalAvailable, $productid);
        $update1->execute();

        $update2 = $connect->prepare("UPDATE from_erply_matrix_products SET quantity = ? WHERE product_id = ?");
        $update2->bind_param("is", $totalAvailable, $productid);
        $update2->execute();

        return [
            'status' => 'success',
            'quantity' => $totalAvailable,
            'warehouses' => $data['records']
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}
