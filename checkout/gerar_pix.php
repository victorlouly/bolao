<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações UTMify
define('UTMIFY_PIXEL_ID', '692bbc5276ee30509ec1a3dd');
define('UTMIFY_API_KEY', '9eiWCeM87Zp8Ldc60KJRNUehJHsIcDtltBXv');
define('UTMIFY_API_URL', 'https://api.utmify.com.br/api-credentials/orders');

// Configurações Facebook Pixel / Meta Conversions API
define('FACEBOOK_PIXEL_ID', '870105399174706');
define('FACEBOOK_ACCESS_TOKEN', 'EAAI2BQZByeB0BQB1sLt631uZAwpt5ekPY4nso2MC9ZCouZBso0xHkJy1ITmxYLzFDS46oyRReTUYhoQ8WvUAGLpDSRTrwDFWBHOScVwAd1c0xS3ZCag4WVoaZAcNNZC3ZBvdNVbbfkrJ5pWoqW61akt7irslw06MVCZCib0hQMwGJTSxhrkW3VyChcccSLHyY0gZDZD');
define('FACEBOOK_TEST_EVENT_CODE', 'TEST18863');
define('FACEBOOK_API_URL', 'https://graph.facebook.com/v18.0/' . FACEBOOK_PIXEL_ID . '/events');

// Receber dados do formulário
$nome = $_POST['nome'] ?? '';
$telefone = $_POST['telefone'] ?? '';
$email = $_POST['email'] ?? '';
$cpf = $_POST['cpf'] ?? '';
$valor = floatval($_POST['valor'] ?? 0);
$bolao = $_POST['bolao'] ?? '';
$tipo_recebimento = $_POST['tipo_recebimento'] ?? 'whatsapp';

// Função para capturar parâmetros UTM do POST ou da URL de referência
function capturarParametrosUTM() {
    $parametrosUTM = [];
    
    // Lista de parâmetros UTM importantes
    $parametrosImportantes = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'xcod', 'sck', 'click_id', 'fbclid', 'gclid', 'msclkid', 'ttclid',
        'src', 'source', 'medium', 'campaign', 'content', 'term',
        'ref', 'referrer', 'affiliate', 'partner', 'promo', 'coupon',
        'subid', 'subid1', 'subid2', 'subid3', 'subid4', 'subid5'
    ];
    
    // Capturar do POST primeiro
    foreach ($parametrosImportantes as $param) {
        if (isset($_POST[$param]) && !empty($_POST[$param])) {
            $parametrosUTM[$param] = $_POST[$param];
        }
    }
    
    // Se não encontrou no POST, tentar capturar do referer
    if (empty($parametrosUTM) && isset($_SERVER['HTTP_REFERER'])) {
        $refererUrl = $_SERVER['HTTP_REFERER'];
        $parsedUrl = parse_url($refererUrl);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            foreach ($parametrosImportantes as $param) {
                if (isset($queryParams[$param]) && !empty($queryParams[$param])) {
                    $parametrosUTM[$param] = $queryParams[$param];
                }
            }
        }
    }
    
    return $parametrosUTM;
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

// Função para enviar evento InitiateCheckout para Facebook Conversions API
function enviarEventoFacebookInitiateCheckout($valorCentavos, $dadosCliente, $parametrosUTM) {
    try {
        $eventTime = time();
        
        // Preparar fbc (Facebook Click ID) se fbclid estiver presente
        $fbc = null;
        if (isset($parametrosUTM['fbclid']) && !empty($parametrosUTM['fbclid'])) {
            $timestamp = $eventTime;
            $fbc = "fb.0.{$timestamp}.{$parametrosUTM['fbclid']}";
        }
        
        // Preparar dados do evento
        $eventData = [
            'data' => [
                [
                    'event_name' => 'InitiateCheckout',
                    'event_time' => $eventTime,
                    'event_id' => uniqid('initiate_checkout_', true), // ID único para deduplicação
                    'event_source_url' => $_SERVER['HTTP_REFERER'] ?? 'https://mega.davirada2026.com/checkout/',
                    'action_source' => 'website',
                    'user_data' => [
                        'em' => !empty($dadosCliente['email']) ? hash('sha256', strtolower(trim($dadosCliente['email']))) : null,
                        'ph' => !empty($dadosCliente['phone']) ? hash('sha256', preg_replace('/\D/', '', $dadosCliente['phone'])) : null,
                        'fn' => !empty($dadosCliente['name']) ? hash('sha256', strtolower(explode(' ', $dadosCliente['name'])[0])) : null,
                        'ln' => !empty($dadosCliente['name']) ? hash('sha256', strtolower(implode(' ', array_slice(explode(' ', $dadosCliente['name']), 1)))) : null,
                        'client_ip_address' => obterIP(),
                        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'fbc' => $fbc, // Facebook Click ID
                        'fbp' => null // Facebook Browser ID (pode ser obtido de cookies se necessário)
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
        
        // Enviar requisição para Facebook Conversions API (assíncrono, não bloqueia)
        $ch = curl_init(FACEBOOK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($eventData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Facebook Conversions API curl error (InitiateCheckout): {$curlError}");
            return ['success' => false, 'error' => $curlError];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Facebook InitiateCheckout event enviado com sucesso - HTTP {$httpCode} - Response: " . json_encode($responseData));
            return ['success' => true, 'http_code' => $httpCode, 'response' => $responseData];
        } else {
            error_log("Facebook Conversions API error (InitiateCheckout): HTTP {$httpCode} - " . json_encode($responseData));
            return ['success' => false, 'http_code' => $httpCode, 'error' => $responseData];
        }
    } catch (Exception $e) {
        error_log("Erro ao enviar evento Facebook InitiateCheckout: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Função para criar order na UTMify
function criarOrderUtmify($orderId, $valorCentavos, $status, $dadosCliente, $parametrosUTM, $createdAt, $approvedDate = null, $refundedAt = null) {
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
        
        // Preparar commission (sem taxa por enquanto)
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
            'status' => $status, // 'waiting_payment', 'paid', 'refunded', etc.
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
        
        // Log para debug
        error_log("UTMify order criado: {$orderId} - Status: {$status} - HTTP {$httpCode}");
        
        return $httpCode >= 200 && $httpCode < 300;
    } catch (Exception $e) {
        error_log("Erro ao criar order UTMify: " . $e->getMessage());
        return false;
    }
}

// Função para obter um email aleatório do arquivo
function obterEmailAleatorio() {
    $arquivoEmails = __DIR__ . '/../media/emails_brasileiros_3000.txt';
    
    // Verificar se o arquivo existe
    if (!file_exists($arquivoEmails)) {
        // Fallback para email padrão se o arquivo não existir
        return 'luizalmeida@gmail.com';
    }
    
    // Ler todas as linhas do arquivo
    $emails = file($arquivoEmails, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // Verificar se há emails no arquivo
    if (empty($emails)) {
        return 'luizalmeida@gmail.com';
    }
    
    // Remover espaços em branco e filtrar linhas vazias
    $emails = array_filter(array_map('trim', $emails));
    
    if (empty($emails)) {
        return 'luizalmeida@gmail.com';
    }
    
    // Retornar um email aleatório
    return $emails[array_rand($emails)];
}

// Se email não foi informado ou está vazio, usar email aleatório do arquivo
if (empty($email) || $email === 'Não informado' || $email === 'nao-informado@nao.com') {
    $email = obterEmailAleatorio();
}

// Validar dados obrigatórios
if (empty($nome) || empty($telefone) || empty($cpf) || $valor <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Dados incompletos. Por favor, preencha todos os campos obrigatórios.'
    ]);
    exit;
}

// Converter valor para centavos (API espera em centavos)
$valorCentavos = intval($valor * 100);

// Gerar externalRef único
$externalRef = uniqid('bolao_', true);

// Obter IP do cliente
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

// Limpar CPF (apenas números)
$cpfLimpo = preg_replace('/\D/', '', $cpf);
$telefoneLimpo = preg_replace('/\D/', '', $telefone);

// Preparar dados para a API
$dadosAPI = [
    'ip' => $ip,
    'pix' => [
        'expiresInDays' => 2
    ],
    'items' => [
        [
            'title' => 'Ebook Isca Digital',
            'quantity' => 1,
            'tangible' => false,
            'unitPrice' => $valorCentavos,
            'externalRef' => $externalRef
        ]
    ],
    'amount' => $valorCentavos,
    'currency' => 'BRL',
    'customer' => [
        'name' => strtoupper($nome),
        'email' => $email,
        'phone' => $telefoneLimpo,
        'document' => [
            'type' => 'CPF',
            'number' => $cpfLimpo
        ]
    ],
    'metadata' => json_encode([
        'bolao' => $bolao,
        'tipo_recebimento' => $tipo_recebimento,
        'externalRef' => $externalRef,
        'orderId' => $externalRef // Para o webhook identificar o orderId da UTMify
    ]),
    'installments' => 1,
    'paymentMethod' => 'PIX',
    'postbackUrl' => 'https://mega.davirada2026.com/webhook.php'
];

// Configurações da API (podem ser movidas para variáveis de ambiente)
$pixApiUrl = 'https://api-gateway.techbynet.com/api/user/transactions';
$pixApiKey = '98290fac-b0ff-4472-8c4c-e1c6f835e973';

// Fazer requisição para a API
$ch = curl_init($pixApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($dadosAPI),
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
        'message' => 'Erro na conexão: ' . $curlError
    ]);
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['data'])) {
    $transactionData = $responseData['data'];
    
    // Extrair dados necessários
    $transactionId = $transactionData['id'] ?? '';
    $pixQrcode = $transactionData['pix']['qrcode'] ?? $transactionData['qrCode'] ?? '';
    
    if (empty($pixQrcode)) {
        echo json_encode([
            'success' => false,
            'message' => 'QR Code não foi gerado pela API.'
        ]);
        exit;
    }
    
    // Capturar parâmetros UTM e criar order na UTMify
    $parametrosUTM = capturarParametrosUTM();
    
    // Preparar dados do cliente
    $dadosCliente = [
        'name' => $nome,
        'email' => $email,
        'phone' => $telefoneLimpo,
        'document' => $cpfLimpo
    ];
    
    // Criar order na UTMify com status waiting_payment
    criarOrderUtmify(
        $externalRef, // orderId
        $valorCentavos,
        'waiting_payment',
        $dadosCliente,
        $parametrosUTM,
        date('c'), // createdAt (ISO 8601)
        null, // approvedDate
        null  // refundedAt
    );
    
    // Enviar evento InitiateCheckout para Facebook Conversions API
    enviarEventoFacebookInitiateCheckout($valorCentavos, $dadosCliente, $parametrosUTM);
    
    echo json_encode([
        'success' => true,
        'transaction_id' => $transactionId,
        'external_ref' => $externalRef, // Para usar como orderId na UTMify
        'pix_qrcode' => $pixQrcode,
        'amount' => $valorCentavos,
        'status' => $transactionData['status'] ?? 'WAITING_PAYMENT'
    ]);
} else {
    $errorMessage = $responseData['message'] ?? 'Erro desconhecido ao gerar PIX';
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'http_code' => $httpCode,
        'response' => $responseData
    ]);
}
?>

