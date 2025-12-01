<?php
header('Content-Type: application/json');

// Configurações UTMify
define('UTMIFY_API_KEY', '9eiWCeM87Zp8Ldc60KJRNUehJHsIcDtltBXv');
define('UTMIFY_API_URL', 'https://api.utmify.com.br/api-credentials/orders');

// Receber dados do webhook
$input = file_get_contents('php://input');

// Log para debug (apenas em desenvolvimento)
if (isset($_GET['debug'])) {
    error_log('Webhook recebido: ' . $input);
}

$data = json_decode($input, true);

// Validar se recebeu dados
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido', 'received' => substr($input, 0, 200)]);
    exit;
}

// A bynet pode enviar diretamente em 'data' ou na raiz
$webhookData = $data['data'] ?? $data;

// Extrair informações da transação
$transactionId = $webhookData['id'] ?? null;
$status = $webhookData['status'] ?? null;
$amount = isset($webhookData['amount']) ? intval($webhookData['amount']) : 0;
$customer = $webhookData['customer'] ?? [];
$metadata = [];

// Tentar decodificar metadata se for string
if (isset($webhookData['metadata'])) {
    if (is_string($webhookData['metadata'])) {
        $metadata = json_decode($webhookData['metadata'], true) ?? [];
    } else {
        $metadata = $webhookData['metadata'];
    }
}

// Extrair externalRef do metadata (usado como orderId na UTMify)
$orderId = $metadata['orderId'] ?? $metadata['externalRef'] ?? $transactionId;

// Mapear status da bynet para status da UTMify
$utmifyStatus = 'waiting_payment';
$approvedDate = null;
$refundedAt = null;

if (in_array(strtolower($status), ['paid', 'authorized'])) {
    $utmifyStatus = 'paid';
    $approvedDate = date('c');
} elseif (in_array(strtolower($status), ['refunded', 'canceled', 'chargedback'])) {
    $utmifyStatus = 'refunded';
    $refundedAt = date('c');
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

// Preparar tracking parameters (vazio por enquanto, pode ser melhorado)
$parametrosUTM = [];

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

// Atualizar order na UTMify se o status mudou para paid/refunded
if ($utmifyStatus === 'paid' || $utmifyStatus === 'refunded' || $utmifyStatus === 'refused') {
    $createdAt = isset($webhookData['createdAt']) ? $webhookData['createdAt'] : date('c');
    
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
}

// Responder com sucesso
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Webhook processado com sucesso',
    'orderId' => $orderId,
    'status' => $utmifyStatus
]);

