<?php
class ErplyAPI
{
    private $clientCode;
    private $username;
    private $password;
    private $apiUrl;
    private $lastRequestInfo = [];
    private $debugMode = true;

    public function __construct($debugMode = true)
    {
        require_once 'config.php';

        // Validate that required constants are defined
        if (!defined('ERPLY_CLIENT_CODE') || !defined('ERPLY_USERNAME') || !defined('ERPLY_PASSWORD')) {
            throw new Exception("Missing required configuration in config.php");
        }

        $this->clientCode = ERPLY_CLIENT_CODE;
        $this->username = ERPLY_USERNAME;
        $this->password = ERPLY_PASSWORD;

        // Construct the API URL using the client code
        $this->apiUrl = "https://" . $this->clientCode . ".erply.com/api/";

        $this->debugMode = $debugMode;
    }

    public function sendRequest($request, $parameters = array())
    {
        $this->lastRequestInfo = [
            'request' => $request,
            'parameters' => $parameters,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Add required authentication parameters
        $parameters['request'] = $request;
        $parameters['clientCode'] = $this->clientCode;
        $parameters['username'] = $this->username;
        $parameters['password'] = md5($this->password); // Erply requires MD5 hashed password

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if (curl_errno($ch)) {
            $errorMsg = "CURL Error: " . curl_error($ch);
            curl_close($ch);
            throw new Exception($errorMsg);
        }

        curl_close($ch);

        // Parse and validate response
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from API");
        }

        // Check for API errors
        if (isset($decoded['status']['errorCode'])) {
            throw new Exception("API Error: " . ($decoded['status']['errorMessage'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    public function getProducts($page = 1, $pageSize = 100, $changedSince = null)
    {
        $params = [
            'pageNo' => $page,
            'recordsOnPage' => $pageSize,
            'getMatrixVariations' => 1,
            'getStockInfo' => 1
        ];

        if ($changedSince) {
            $params['changedSince'] = is_numeric($changedSince) ? $changedSince : strtotime($changedSince);
        }

        return $this->sendRequest("getProducts", $params);
    }

    public function getProductStock($productId, $warehouseId = 0)
    {
        return $this->sendRequest("getProductStock", [
            'productID' => $productId,
            'warehouseID' => $warehouseId,
            'getAmountReserved' => 1
        ]);
    }

    public function verifyConnection()
    {
        try {
            $response = $this->sendRequest("verifyUser");
            return isset($response['status']['responseStatus']) && $response['status']['responseStatus'] === 'ok';
        } catch (Exception $e) {
            return false;
        }
    }
}
