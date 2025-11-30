<?php
header("Content-Type: application/json");

$result = [
    "teste" => "API de Pagamento",
    "php_version" => phpversion(),
    "curl_disponivel" => function_exists('curl_init'),
    "arquivo_keys_existe" => file_exists(__DIR__ . "/keys.env"),
    "metodo_request" => $_SERVER['REQUEST_METHOD']
];

// Testar leitura do keys.env
if (file_exists(__DIR__ . "/keys.env")) {
    $lines = file(__DIR__ . "/keys.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $keys = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), "#") === 0) continue;
        $parts = explode("=", $line, 2);
        if (count($parts) == 2) {
            $key = trim($parts[0]);
            $keys[] = $key . " = " . (empty(trim($parts[1])) ? "VAZIO" : "configurado");
        }
    }
    $result["chaves_encontradas"] = $keys;
}

// Testar recebimento de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents("php://input");
    $result["input_recebido"] = $input;
    $result["input_parseado"] = json_decode($input, true);
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>