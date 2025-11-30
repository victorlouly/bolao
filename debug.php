<?php
header("Content-Type: application/json");

$result = [
    "php_version" => phpversion(),
    "curl_disponivel" => function_exists('curl_init'),
    "arquivo_keys_existe" => file_exists(__DIR__ . "/keys.env"),
    "diretorio_atual" => __DIR__,
    "permissoes_keys" => file_exists(__DIR__ . "/keys.env") ? substr(sprintf('%o', fileperms(__DIR__ . "/keys.env")), -4) : "arquivo não existe"
];

// Tentar ler keys.env
if (file_exists(__DIR__ . "/keys.env")) {
    $lines = file(__DIR__ . "/keys.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $keys_encontradas = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), "#") === 0) continue;
        $parts = explode("=", $line, 2);
        if (count($parts) == 2) {
            $key = trim($parts[0]);
            $keys_encontradas[] = $key . " = " . (empty(trim($parts[1])) ? "VAZIO" : "configurado");
        }
    }
    $result["chaves_no_arquivo"] = $keys_encontradas;
}

// Testar cURL
if (function_exists('curl_init')) {
    $ch = curl_init("https://www.google.com");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    $result["teste_curl"] = $error ? "ERRO: " . $error : "OK";
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>