<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

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

// Fazer requisição para verificar status
$ch = curl_init("https://api-gateway.techbynet.com/api/user/transactions/{$transactionId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
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
        'status' => 'error',
        'message' => 'Erro na conexão: ' . $curlError
    ]);
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['data'])) {
    $transactionData = $responseData['data'];
    $status = $transactionData['status'] ?? 'WAITING_PAYMENT';
    
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

