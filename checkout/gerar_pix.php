<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações UTMify
define('UTMIFY_PIXEL_ID', '692bbc5276ee30509ec1a3dd');
define('UTMIFY_API_ID', '9eiWCeM87Zp8Ldc60KJRNUehJHsIcDtltBXv');
define('UTMIFY_API_URL', 'https://tracking.utmify.com.br/tracking/v1/events');

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
            'ipv6' => null, // Pode ser obtido se necessário
            'parameters' => $parameters,
            'fbc' => null, // Facebook click ID (pode ser extraído dos parâmetros)
            'fbp' => null, // Facebook browser ID (pode ser extraído dos cookies)
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
            // Formatar fbc no padrão: fb.0.timestamp.fbclid
            $timestamp = time();
            $lead['fbc'] = "fb.0.{$timestamp}.{$parametrosUTM['fbclid']}";
        }
        
        // Preparar dados do evento conforme estrutura da UTMify
        $dadosEvento = [
            'type' => $tipoEvento, // 'Purchase', 'InitiateCheckout', 'Conversion'
            'lead' => $lead,
            'event' => [
                'sourceUrl' => $_SERVER['HTTP_REFERER'] ?? '',
                'pageTitle' => 'Pagamento PIX - Ebook Isca Digital'
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
            CURLOPT_TIMEOUT => 3, // Timeout curto para não bloquear
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        
        // Executar de forma assíncrona (não esperar resposta)
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log para debug (opcional)
        error_log("UTMify evento enviado: {$tipoEvento} - HTTP {$httpCode}");
        
        return true;
    } catch (Exception $e) {
        // Log do erro (opcional, não interrompe o fluxo)
        error_log("Erro ao enviar evento UTMify: " . $e->getMessage());
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
        'externalRef' => $externalRef
    ]),
    'installments' => 1,
    'paymentMethod' => 'PIX',
    'postbackUrl' => 'https://webhook.site/e43a67f2-f174-4998-bcb2-2c3888a3e6d4'
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
    
    // Capturar parâmetros UTM e enviar eventos para UTMify
    $parametrosUTM = capturarParametrosUTM();
    
    // Preparar dados do cliente
    $dadosCliente = [
        'email' => $email,
        'firstName' => explode(' ', $nome)[0] ?? '',
        'lastName' => implode(' ', array_slice(explode(' ', $nome), 1)) ?? '',
        'phone' => $telefoneLimpo
    ];
    
    // Enviar eventos para UTMify
    enviarEventoUTMify('InitiateCheckout', $valorCentavos / 100, $parametrosUTM, $dadosCliente);
    enviarEventoUTMify('Purchase', $valorCentavos / 100, $parametrosUTM, $dadosCliente);
    
    echo json_encode([
        'success' => true,
        'transaction_id' => $transactionId,
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

