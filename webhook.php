<?php
header('Content-Type: application/json');

// Configurações
define('UTMIFY_API_KEY', '9eiWCeM87Zp8Ldc60KJRNUehJHsIcDtltBXv');
define('UTMIFY_API_URL', 'https://api.utmify.com.br/api-credentials/orders');
define('BYNET_API_URL', 'https://api-gateway.techbynet.com/api/user/transactions');
define('BYNET_API_KEY', '98290fac-b0ff-4472-8c4c-e1c6f835e973');

// Configurações Facebook Pixel / Meta Conversions API
define('FACEBOOK_PIXEL_ID', '870105399174706');
define('FACEBOOK_ACCESS_TOKEN', 'EAAI2BQZByeB0BQB1sLt631uZAwpt5ekPY4nso2MC9ZCouZBso0xHkJy1ITmxYLzFDS46oyRReTUYhoQ8WvUAGLpDSRTrwDFWBHOScVwAd1c0xS3ZCag4WVoaZAcNNZC3ZBvdNVbbfkrJ5pWoqW61akt7irslw06MVCZCib0hQMwGJTSxhrkW3VyChcccSLHyY0gZDZD');
define('FACEBOOK_TEST_EVENT_CODE', 'TEST18863');
define('FACEBOOK_API_URL', 'https://graph.facebook.com/v18.0/' . FACEBOOK_PIXEL_ID . '/events');

// Receber dados do webhook
$input = file_get_contents('php://input');

// Log para debug (apenas em desenvolvimento)
if (isset($_GET['debug'])) {
    error_log('Webhook recebido: ' . $input);
}

$data = json_decode($input, true);

// Validar se recebeu dados
if (!$data || !isset($data['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos', 'received' => substr($input, 0, 200)]);
    exit;
}

// Extrair informações do webhook
$objectId = $data['objectId'] ?? null; // transaction ID
$webhookData = $data['data'];
$status = $webhookData['status'] ?? null;
$paidAt = $webhookData['paidAt'] ?? null;
$endToEndId = $webhookData['endToEndId'] ?? null;

if (!$objectId || !$status) {
    http_response_code(400);
    echo json_encode(['error' => 'objectId ou status não encontrado']);
    exit;
}

// Buscar dados completos da transação na API da bynet
$ch = curl_init(BYNET_API_URL . '/' . $objectId);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . BYNET_API_KEY,
        'User-Agent: BolaoApp/1.0'
    ],
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    error_log("Erro ao buscar transação {$objectId}: {$curlError} - HTTP {$httpCode}");
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar dados da transação']);
    exit;
}

$transactionData = json_decode($response, true);

if (!isset($transactionData['data'])) {
    error_log("Transação {$objectId} não encontrada na resposta");
    http_response_code(404);
    echo json_encode(['error' => 'Transação não encontrada']);
    exit;
}

$transaction = $transactionData['data'];

// Extrair informações da transação
$amount = isset($transaction['amount']) ? intval($transaction['amount']) : 0;
$customer = $transaction['customer'] ?? [];
$metadata = [];

// Tentar decodificar metadata se for string
if (isset($transaction['metadata'])) {
    if (is_string($transaction['metadata'])) {
        $metadata = json_decode($transaction['metadata'], true) ?? [];
    } else {
        $metadata = $transaction['metadata'];
    }
}

// Extrair externalRef do metadata (usado como orderId na UTMify)
$orderId = $metadata['orderId'] ?? $metadata['externalRef'] ?? $objectId;

// Mapear status da bynet para status da UTMify
$utmifyStatus = 'waiting_payment';
$approvedDate = null;
$refundedAt = null;

if (in_array(strtolower($status), ['paid', 'authorized'])) {
    $utmifyStatus = 'paid';
    // Usar paidAt do webhook se disponível, senão usar data atual
    $approvedDate = $paidAt ? date('c', strtotime($paidAt)) : date('c');
} elseif (in_array(strtolower($status), ['refunded', 'canceled', 'chargedback'])) {
    $utmifyStatus = 'refunded';
    // Verificar se há refunds no webhook
    if (isset($webhookData['refunds']) && !empty($webhookData['refunds'])) {
        $firstRefund = $webhookData['refunds'][0];
        $refundedAt = isset($firstRefund['createdAt']) ? date('c', strtotime($firstRefund['createdAt'])) : date('c');
    } else {
        $refundedAt = date('c');
    }
} elseif (in_array(strtolower($status), ['refused'])) {
    $utmifyStatus = 'refused';
}

// Preparar dados do cliente
$dadosCliente = [
    'name' => $customer['name'] ?? '',
    'email' => $customer['email'] ?? '',
    'phone' => $customer['phone'] ?? null,
    'document' => isset($customer['document']['number']) ? $customer['document']['number'] : null
];

// Recuperar parâmetros UTM do metadata
$parametrosUTM = $metadata['utm_params'] ?? [];
$fbc = $metadata['fbc'] ?? null;
$fbp = $metadata['fbp'] ?? null;
$fbclid = $metadata['fbclid'] ?? null;

// Se não encontrou no metadata, tentar reconstruir fbc do fbclid
if (!$fbc && $fbclid && isset($transaction['createdAt'])) {
    $timestamp = strtotime($transaction['createdAt']);
    $fbc = "fb.0.{$timestamp}.{$fbclid}";
}

// Função para enviar evento Purchase para Facebook Conversions API
function enviarEventoFacebookPurchase($valorCentavos, $dadosCliente, $externalRef, $fbc = null, $fbp = null, $eventTime = null) {
    try {
        $eventTime = $eventTime ?? time();
        
        // Usar externalRef como base do event_id para deduplicação e vincular com InitiateCheckout
        $eventId = "purchase_{$externalRef}";
        
        // Preparar dados do evento
        $eventData = [
            'data' => [
                [
                    'event_name' => 'Purchase',
                    'event_time' => $eventTime,
                    'event_id' => $eventId, // ID baseado no externalRef para deduplicação
                    'event_source_url' => 'https://mega.davirada2026.com/checkout/',
                    'action_source' => 'website',
                    'user_data' => [
                        'em' => !empty($dadosCliente['email']) ? hash('sha256', strtolower(trim($dadosCliente['email']))) : null,
                        'ph' => !empty($dadosCliente['phone']) ? hash('sha256', preg_replace('/\D/', '', $dadosCliente['phone'])) : null,
                        'fn' => !empty($dadosCliente['name']) ? hash('sha256', strtolower(explode(' ', $dadosCliente['name'])[0])) : null,
                        'ln' => !empty($dadosCliente['name']) ? hash('sha256', strtolower(implode(' ', array_slice(explode(' ', $dadosCliente['name']), 1)))) : null,
                        'client_ip_address' => obterIP(),
                        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'fbc' => $fbc, // Facebook Click ID (recuperado do metadata)
                        'fbp' => $fbp // Facebook Browser ID (recuperado do metadata)
                    ],
                    'custom_data' => [
                        'currency' => 'BRL',
                        'value' => $valorCentavos / 100, // Converter centavos para reais
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
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Facebook Conversions API curl error (Purchase): {$curlError}");
            return ['success' => false, 'error' => $curlError];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Facebook Purchase event enviado com sucesso - HTTP {$httpCode} - Response: " . json_encode($responseData));
            return ['success' => true, 'http_code' => $httpCode, 'response' => $responseData];
        } else {
            error_log("Facebook Conversions API error (Purchase): HTTP {$httpCode} - " . json_encode($responseData));
            return ['success' => false, 'http_code' => $httpCode, 'error' => $responseData];
        }
    } catch (Exception $e) {
        error_log("Erro ao enviar evento Facebook Purchase: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Função para obter IP do cliente
function obterIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return $ip;
}

// Função para atualizar order na UTMify
function atualizarOrderUtmify($orderId, $valorCentavos, $status, $dadosCliente, $parametrosUTM, $createdAt, $approvedDate = null, $refundedAt = null) {
    try {
        // Preparar dados do cliente
        $customer = [
            'name' => $dadosCliente['name'] ?? '',
            'email' => $dadosCliente['email'] ?? '',
            'phone' => $dadosCliente['phone'] ?? null,
            'document' => $dadosCliente['document'] ?? null,
            'ip' => obterIP()
        ];
        
        // Preparar produtos
        $products = [
            [
                'id' => '1',
                'name' => 'Ebook Isca Digital',
                'priceInCents' => $valorCentavos,
                'quantity' => 1,
                'planId' => null,
                'planName' => null
            ]
        ];
        
        // Preparar tracking parameters
        $trackingParameters = [
            'src' => $parametrosUTM['src'] ?? $parametrosUTM['utm_source'] ?? null,
            'sck' => $parametrosUTM['sck'] ?? null,
            'utm_source' => $parametrosUTM['utm_source'] ?? null,
            'utm_campaign' => $parametrosUTM['utm_campaign'] ?? null,
            'utm_medium' => $parametrosUTM['utm_medium'] ?? null,
            'utm_content' => $parametrosUTM['utm_content'] ?? null,
            'utm_term' => $parametrosUTM['utm_term'] ?? null
        ];
        
        // Preparar commission
        $commission = [
            'totalPriceInCents' => $valorCentavos,
            'gatewayFeeInCents' => 0,
            'userCommissionInCents' => $valorCentavos,
            'currency' => 'BRL'
        ];
        
        // Usar createdAt do webhook ou data atual
        $createdAt = $createdAt ?? date('c');
        
        // Preparar payload completo
        $payload = [
            'orderId' => $orderId,
            'platform' => 'default_checkout',
            'paymentMethod' => 'pix',
            'status' => $status,
            'createdAt' => $createdAt,
            'approvedDate' => $approvedDate,
            'refundedAt' => $refundedAt,
            'customer' => $customer,
            'products' => $products,
            'trackingParameters' => $trackingParameters,
            'commission' => $commission,
            'isTest' => false
        ];
        
        // Enviar requisição para UTMify
        $ch = curl_init(UTMIFY_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-token: ' . UTMIFY_API_KEY
            ],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("UTMify curl error no webhook: {$curlError}");
            return false;
        }
        
        error_log("UTMify order atualizado via webhook: {$orderId} - Status: {$status} - HTTP {$httpCode}");
        
        return $httpCode >= 200 && $httpCode < 300;
    } catch (Exception $e) {
        error_log("Erro ao atualizar order UTMify no webhook: " . $e->getMessage());
        return false;
    }
}

// Atualizar order na UTMify se o status mudou para paid/refunded/refused
if ($utmifyStatus === 'paid' || $utmifyStatus === 'refunded' || $utmifyStatus === 'refused') {
    // Usar createdAt da transação ou data atual
    $createdAt = isset($transaction['createdAt']) ? $transaction['createdAt'] : date('c');
    
    // Converter para formato ISO se necessário
    if ($createdAt && !strpos($createdAt, 'T')) {
        $createdAt = date('c', strtotime($createdAt));
    }
    
    // Atualizar order na UTMify
    atualizarOrderUtmify(
        $orderId,
        $amount,
        $utmifyStatus,
        $dadosCliente,
        $parametrosUTM,
        $createdAt,
        $approvedDate,
        $refundedAt
    );
    
    // Enviar evento Purchase para Facebook Conversions API apenas quando pagamento for confirmado
    if ($utmifyStatus === 'paid') {
        $eventTime = $paidAt ? strtotime($paidAt) : time();
        enviarEventoFacebookPurchase($amount, $dadosCliente, $orderId, $fbc, $fbp, $eventTime);
    }
}

// Responder com sucesso
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Webhook processado com sucesso',
    'orderId' => $orderId,
    'status' => $utmifyStatus
]);

