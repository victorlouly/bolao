<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações UTMify
define('UTMIFY_PIXEL_ID', '692bbc5276ee30509ec1a3dd');
define('UTMIFY_API_KEY', '9eiWCeM87Zp8Ldc60KJRNUehJHsIcDtltBXv');
define('UTMIFY_API_URL', 'https://api.utmify.com.br/api-credentials/orders');

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
            error_log("UTMify curl error: {$curlError}");
            return false;
        }
        
        error_log("UTMify order atualizado: {$orderId} - Status: {$status} - HTTP {$httpCode}");
        
        return $httpCode >= 200 && $httpCode < 300;
    } catch (Exception $e) {
        error_log("Erro ao atualizar order UTMify: " . $e->getMessage());
        return false;
    }
}

// Receber ID da transação
$transactionId = $_GET['id'] ?? '';

if (empty($transactionId)) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'ID da transação não fornecido.'
    ]);
    exit;
}

// Configurações da API (podem ser movidas para variáveis de ambiente)
$pixApiUrl = 'https://api-gateway.techbynet.com/api/user/transactions';
$pixApiKey = '98290fac-b0ff-4472-8c4c-e1c6f835e973';

// Fazer requisição para verificar status
$ch = curl_init("{$pixApiUrl}/{$transactionId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $pixApiKey,
        'User-Agent: AtivoB2B/1.0'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Erro na conexão: ' . $curlError
    ]);
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['data'])) {
    $transactionData = $responseData['data'];
    $status = $transactionData['status'] ?? 'WAITING_PAYMENT';
    
    // Se o pagamento foi confirmado, atualizar order na UTMify
    if (in_array(strtoupper($status), ['PAID', 'APPROVED', 'CONFIRMED'])) {
        $valorCentavos = isset($transactionData['amount']) ? intval($transactionData['amount']) : 0;
        
        // Tentar capturar parâmetros UTM do GET ou do referer
        $parametrosUTM = [];
        $parametrosImportantes = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            'src', 'sck', 'xcod', 'click_id', 'fbclid', 'gclid', 'msclkid', 'ttclid'
        ];
        
        foreach ($parametrosImportantes as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $parametrosUTM[$param] = $_GET[$param];
            }
        }
        
        // Obter dados do cliente da transação
        $customerData = $transactionData['customer'] ?? [];
        $dadosCliente = [
            'name' => $customerData['name'] ?? '',
            'email' => $customerData['email'] ?? '',
            'phone' => $customerData['phone'] ?? null,
            'document' => isset($customerData['document']['number']) ? $customerData['document']['number'] : null
        ];
        
        // Tentar obter externalRef do localStorage via JavaScript ou usar transactionId
        // Por enquanto, vamos usar o transactionId como orderId
        // O ideal seria passar o externalRef via GET ou salvar em banco
        $orderId = $transactionId; // Pode ser substituído por externalRef se disponível
        $createdAt = isset($transactionData['createdAt']) ? $transactionData['createdAt'] : date('c');
        $approvedDate = date('c'); // Data de aprovação = agora
        $refundedAt = null;
        
        // Atualizar order na UTMify com status 'paid'
        atualizarOrderUtmify(
            $orderId,
            $valorCentavos,
            'paid',
            $dadosCliente,
            $parametrosUTM,
            $createdAt,
            $approvedDate,
            $refundedAt
        );
    }
    
    echo json_encode([
        'success' => true,
        'status' => $status,
        'transaction_id' => $transactionId,
        'paid_at' => $transactionData['paidAt'] ?? null
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $responseData['message'] ?? 'Erro ao verificar status',
        'http_code' => $httpCode
    ]);
}
?>

