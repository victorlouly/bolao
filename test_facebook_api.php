<?php
/**
 * Script de teste para verificar se a API de Conversão do Meta está funcionando
 * Acesse: https://mega.davirada2026.com/test_facebook_api.php
 */

// Configurações Facebook Pixel / Meta Conversions API
define('FACEBOOK_PIXEL_ID', '870105399174706');
define('FACEBOOK_ACCESS_TOKEN', 'EAAI2BQZByeB0BQB1sLt631uZAwpt5ekPY4nso2MC9ZCouZBso0xHkJy1ITmxYLzFDS46oyRReTUYhoQ8WvUAGLpDSRTrwDFWBHOScVwAd1c0xS3ZCag4WVoaZAcNNZC3ZBvdNVbbfkrJ5pWoqW61akt7irslw06MVCZCib0hQMwGJTSxhrkW3VyChcccSLHyY0gZDZD');
define('FACEBOOK_TEST_EVENT_CODE', 'TEST96702');
define('FACEBOOK_API_URL', 'https://graph.facebook.com/v18.0/' . FACEBOOK_PIXEL_ID . '/events');

header('Content-Type: application/json');

// Função para obter IP
function obterIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return $ip;
}

// Dados de teste
$eventTime = time();
$testEmail = 'teste@exemplo.com';
$testPhone = '5511999999999';
$testName = 'Teste Usuario';
$testValue = 39.90;

// Preparar dados do evento de teste
$eventData = [
    'data' => [
        [
            'event_name' => 'InitiateCheckout',
            'event_time' => $eventTime,
            'event_id' => 'test_' . uniqid(),
            'event_source_url' => 'https://mega.davirada2026.com/checkout/',
            'action_source' => 'website',
            'user_data' => [
                'em' => hash('sha256', strtolower(trim($testEmail))),
                'ph' => hash('sha256', preg_replace('/\D/', '', $testPhone)),
                'fn' => hash('sha256', strtolower(explode(' ', $testName)[0])),
                'ln' => hash('sha256', strtolower(implode(' ', array_slice(explode(' ', $testName), 1)))),
                'client_ip_address' => obterIP(),
                'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Test Script'
            ],
            'custom_data' => [
                'currency' => 'BRL',
                'value' => $testValue,
                'content_name' => 'Ebook Isca Digital',
                'content_category' => 'Digital Product'
            ]
        ]
    ],
    'access_token' => FACEBOOK_ACCESS_TOKEN,
    'test_event_code' => FACEBOOK_TEST_EVENT_CODE
];

// Enviar requisição para Facebook Conversions API
$ch = curl_init(FACEBOOK_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($eventData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$result = [
    'pixel_id' => FACEBOOK_PIXEL_ID,
    'api_url' => FACEBOOK_API_URL,
    'http_code' => $httpCode,
    'curl_error' => $curlError ?: null,
    'response' => json_decode($response, true),
    'raw_response' => $response,
    'success' => ($httpCode >= 200 && $httpCode < 300) && !$curlError,
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($result, JSON_PRETTY_PRINT);

