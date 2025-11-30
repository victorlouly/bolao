<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Receber dados do formulário
$nome = $_POST['nome'] ?? '';
$telefone = $_POST['telefone'] ?? '';
$email = $_POST['email'] ?? 'nao-informado@nao.com';
$cpf = $_POST['cpf'] ?? '';
$valor = floatval($_POST['valor'] ?? 0);
$bolao = $_POST['bolao'] ?? '';
$tipo_recebimento = $_POST['tipo_recebimento'] ?? 'whatsapp';

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
            'title' => 'Bolão da Mega da Virada',
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

// Fazer requisição para a API
$ch = curl_init('https://api-gateway.techbynet.com/api/user/transactions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($dadosAPI),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: 98290fac-b0ff-4472-8c4c-e1c6f835e973',
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

