<?php
// webhook.php - receives postbacks from TechByNet and updates orders.json
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$body = file_get_contents('php://input');
$payload = json_decode($body, true);

$ordersFile = __DIR__ . '/orders.json';
$orders = [];
if(file_exists($ordersFile)) $orders = json_decode(file_get_contents($ordersFile), true) ?? [];

if(!$payload){
    http_response_code(400);
    echo json_encode(['error'=>'invalid_payload']);
    exit;
}

// A API TechByNet pode enviar os dados em diferentes formatos
$data = $payload['data'] ?? $payload;

// Extrair informações da transação
$externalRef = $data['externalRef'] ?? ($data['payload']['data']['items'][0]['externalRef'] ?? null);
$id = $data['id'] ?? null;
$status = $data['status'] ?? null;
$amount = $data['amount'] ?? null;
$customer = $data['customer'] ?? null;

// Log para debug (opcional)
error_log("Webhook recebido: " . json_encode($payload));

// Atualizar ou criar registro
if($externalRef && isset($orders[$externalRef])){
    $orders[$externalRef]['status'] = $status ?? $orders[$externalRef]['status'];
    $orders[$externalRef]['payload'] = $data;
    $orders[$externalRef]['updatedAt'] = date('c');
    if ($amount) $orders[$externalRef]['amount'] = $amount;
    if ($customer) $orders[$externalRef]['customer'] = $customer;
    file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT));
} else if($id){
    // Buscar por ID
    $found = false;
    foreach($orders as $k=>$v){
        if(isset($v['id']) && $v['id']==$id){
            $orders[$k]['status'] = $status ?? $orders[$k]['status'];
            $orders[$k]['payload'] = $data;
            $orders[$k]['updatedAt'] = date('c');
            if ($amount) $orders[$k]['amount'] = $amount;
            if ($customer) $orders[$k]['customer'] = $customer;
            file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT));
            $found = true;
            break;
        }
    }
    if (!$found && $externalRef) {
        // Criar novo registro se não encontrado
        $orders[$externalRef] = [
            'externalRef' => $externalRef,
            'id' => $id,
            'status' => $status,
            'amount' => $amount,
            'customer' => $customer,
            'payload' => $data,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
        file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT));
    }
} else {
    // Criar novo registro com referência gerada
    $ref = 'webhook-' . time();
    $orders[$ref] = [
        'externalRef' => $externalRef ?? $ref,
        'id' => $id,
        'status' => $status,
        'amount' => $amount,
        'customer' => $customer,
        'payload' => $data,
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ];
    file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT));
}

echo json_encode(['ok'=>true, 'received'=>true]);
?>
