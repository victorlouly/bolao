<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações UTMify
define('UTMIFY_PIXEL_ID', '692bbc5276ee30509ec1a3dd');
define('UTMIFY_API_ID', '9eiWCeM87Zp8Ldc60KJRNUehJHsIcDtltBXv');
define('UTMIFY_API_URL', 'https://tracking.utmify.com.br/tracking/v1/events');

// Função para obter IP do cliente
function obterIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return $ip;
}

// Função para formatar parâmetros UTM como query string
function formatarParametrosUTM($parametrosUTM) {
    if (empty($parametrosUTM)) {
        return '';
    }
    return '?' . http_build_query($parametrosUTM);
}

// Função para enviar evento de conversão para UTMify
function enviarEventoUTMify($tipoEvento, $valor, $parametrosUTM = [], $dadosCliente = []) {
    try {
        // Obter IP
        $ip = obterIP();
        
        // Formatar parâmetros UTM como query string
        $parameters = formatarParametrosUTM($parametrosUTM);
        
        // Preparar objeto lead conforme estrutura da UTMify
        $lead = [
            'pixelId' => UTMIFY_PIXEL_ID,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $ip,
            'ipv6' => null,
            'parameters' => $parameters,
            'fbc' => null,
            'fbp' => null,
        ];
        
        // Adicionar dados do cliente se disponíveis
        if (!empty($dadosCliente['email'])) {
            $lead['email'] = $dadosCliente['email'];
        }
        if (!empty($dadosCliente['firstName'])) {
            $lead['firstName'] = $dadosCliente['firstName'];
        }
        if (!empty($dadosCliente['lastName'])) {
            $lead['lastName'] = $dadosCliente['lastName'];
        }
        if (!empty($dadosCliente['phone'])) {
            $lead['phone'] = $dadosCliente['phone'];
        }
        
        // Extrair fbclid dos parâmetros UTM
        if (isset($parametrosUTM['fbclid']) && !empty($parametrosUTM['fbclid'])) {
            $timestamp = time();
            $lead['fbc'] = "fb.0.{$timestamp}.{$parametrosUTM['fbclid']}";
        }
        
        // Preparar dados do evento conforme estrutura da UTMify
        $dadosEvento = [
            'type' => $tipoEvento,
            'lead' => $lead,
            'event' => [
                'sourceUrl' => $_SERVER['HTTP_REFERER'] ?? '',
                'pageTitle' => 'Pagamento Confirmado - Ebook Isca Digital'
            ]
        ];
        
        // Enviar requisição para UTMify (assíncrono, não bloqueia)
        $ch = curl_init(UTMIFY_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dadosEvento),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: BolaoApp/1.0'
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("UTMify evento enviado: {$tipoEvento} - HTTP {$httpCode}");
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar evento UTMify: " . $e->getMessage());
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
    
    // Se o pagamento foi confirmado, enviar evento para UTMify
    if (in_array(strtoupper($status), ['PAID', 'APPROVED', 'CONFIRMED'])) {
        $valor = isset($transactionData['amount']) ? ($transactionData['amount'] / 100) : 0;
        
        // Tentar capturar parâmetros UTM do GET ou do referer
        $parametrosUTM = [];
        $parametrosImportantes = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            'xcod', 'sck', 'click_id', 'fbclid', 'gclid', 'msclkid', 'ttclid'
        ];
        
        foreach ($parametrosImportantes as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $parametrosUTM[$param] = $_GET[$param];
            }
        }
        
        // Enviar evento de pagamento confirmado
        enviarEventoUTMify('Purchase', $valor, $parametrosUTM);
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

