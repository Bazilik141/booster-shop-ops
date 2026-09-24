<?php
namespace Opencart\System\Library\Pintanovaposhta;
/** Test-only API double. Never sends a network request or reads credentials. */
class PintaNovaPoshtaApi {
    public static int $calls = 0;
    public static int $constructed = 0;
    public static array $properties = [];
    public static array $result = ['curl_error' => '', 'http_code' => 200, 'api_response' => ['success' => true, 'data' => [['Cost' => '95.00']]]];
    public function __construct() { self::$constructed++; }
    public function callApi($model, $method, $properties) {
        if ($model !== 'InternetDocument' || $method !== 'getDocumentPrice') throw new \RuntimeException('Unexpected external operation');
        self::$calls++;
        self::$properties = $properties;
        return self::$result;
    }
}
