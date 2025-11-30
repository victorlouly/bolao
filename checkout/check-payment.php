<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações UTMify
define('UTMIFY_PIXEL_ID', '692bbc5276ee30509ec1a3dd');
define('UTMIFY_API_ID', '9eiWCeM87Zp8Ldc60KJRNUehJHsIcDtltBXv');
define('UTMIFY_API_URL', 'https://api.utmify.com.br/v1/events');

// Função para enviar evento de conversão para UTMify
function enviarEventoUTMify($evento, $valor, $parametrosUTM = []) {
    try {
        // Preparar dados do evento
        $dadosEvento = [
            'pixel_id' => UTMIFY_PIXEL_ID,
            'api_id' => UTMIFY_API_ID,
            'event' => $evento,
            'value' => $valor,
            'currency' => 'BRL',
            'timestamp' => time()
        ];
        
        // Adicionar parâmetros UTM
        if (!empty($parametrosUTM)) {
            $dadosEvento['utm_params'] = $parametrosUTM;
        }
        
        // Adicionar informações adicionais
        $dadosEvento['ip'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $dadosEvento['ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        $dadosEvento['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $dadosEvento['referer'] = $_SERVER['HTTP_REFERER'] ?? '';
        
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
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1
        ]);
        
        curl_exec($ch);
        curl_close($ch);
        
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
        enviarEventoUTMify('payment_confirmed', $valor, $parametrosUTM);
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

