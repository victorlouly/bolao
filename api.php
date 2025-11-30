<?php
// Desabilitar exibição de erros HTML
ini_set('display_errors', 0);
error_reporting(0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Carregar variáveis de ambiente (prioridade) ou do arquivo keys.env
    $apiKey = getenv("TECHBYNET_API_KEY");
    
    // Se não estiver nas variáveis de ambiente, tentar ler do arquivo keys.env
    if (!$apiKey || $apiKey === 'your_techbynet_key_here') {
        $envPath = __DIR__ . "/keys.env";
        
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), "#") === 0) continue;
                $parts = explode("=", $line, 2);
                if (count($parts) == 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);
                    if (empty(getenv($key))) {
                        putenv("$key=$val");
                        $_ENV[$key] = $val;
                    }
                }
            }
            $apiKey = getenv("TECHBYNET_API_KEY");
        }
    }

    if (!$apiKey || $apiKey === 'your_techbynet_key_here') {
        throw new Exception("Chave TECHBYNET_API_KEY não configurada");
    }

    // Ler input JSON
    $inputRaw = file_get_contents("php://input");
    $input = json_decode($inputRaw, true);

    if (!$input) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "invalid_input",
            "message" => "Dados de entrada inválidos"
        ]);
        exit;
    }

    $valor = isset($input["valor"]) ? floatval($input["valor"]) : 0;
    $cotas = isset($input["cotas"]) ? $input["cotas"] : "";
    $nome = isset($input["nome"]) ? trim($input["nome"]) : "Cliente";
    $cpf = isset($input["cpf"]) ? preg_replace('/\D/', '', $input["cpf"]) : "";
    $email = isset($input["email"]) ? trim($input["email"]) : "";
    $telefone = isset($input["telefone"]) ? preg_replace('/\D/', '', $input["telefone"]) : "";

    if ($valor <= 0) {
        throw new Exception("Valor inválido");
    }

    if (empty($cpf) || strlen($cpf) != 11) {
        throw new Exception("CPF inválido");
    }

    // Validações básicas
    if (empty($email)) {
        $email = "cliente@exemplo.com"; // Fallback
    }

    if (empty($telefone) || strlen($telefone) < 10) {
        $telefone = "11999999999"; // Fallback
    }

    // Verificar se cURL está disponível
    if (!function_exists('curl_init')) {
        throw new Exception("Extensão cURL não está instalada");
    }

    // Obter IP do cliente
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '177.181.6.97';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    // Gerar externalRef único
    $externalRef = "bolao-" . time() . "-" . substr(md5($cpf . $valor), 0, 8);

    // Montar payload conforme especificação da API TechByNet
    $payload = [
        "ip" => $ip,
        "pix" => [
            "expiresInDays" => 2
        ],
        "items" => [
            [
                "title" => "Bolão Mega da Virada - $cotas Cotas",
                "quantity" => 1,
                "tangible" => false,
                "unitPrice" => intval(round($valor * 100)),
                "externalRef" => $externalRef
            ]
        ],
        "amount" => intval(round($valor * 100)),
        "currency" => "BRL",
        "customer" => [
            "name" => strtoupper($nome),
            "email" => $email,
            "phone" => $telefone,
            "document" => [
                "type" => "CPF",
                "number" => $cpf
            ]
        ],
        "metadata" => json_encode([
            "orderId" => $externalRef,
            "cotas" => $cotas,
            "tipo" => "bolao"
        ]),
        "installments" => 1,
        "paymentMethod" => "PIX",
        "postbackUrl" => "https://mega.davirada2026.com/webhook.php"
    ];

    // Fazer requisição para a API TechByNet
    $ch = curl_init("https://api-gateway.techbynet.com/api/user/transactions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "x-api-key: " . $apiKey,
        "User-Agent: AtivoB2B/1.0"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new Exception("Erro cURL: " . $err);
    }

    if ($httpCode === 0) {
        throw new Exception("Não foi possível conectar à API TechByNet");
    }

    // Validar se a resposta é JSON
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Resposta inválida da API TechByNet");
    }

    // Processar resposta e formatar para o frontend
    if ($httpCode == 200 && isset($responseData['status']) && $responseData['status'] == 200 && isset($responseData['data'])) {
        $data = $responseData['data'];
        
        // Extrair QR Code do PIX
        $qrcode = '';
        $expirationDate = '';
        
        if (isset($data['pix']['qrcode'])) {
            $qrcode = $data['pix']['qrcode'];
        } elseif (isset($data['qrCode'])) {
            $qrcode = $data['qrCode'];
        }
        
        if (isset($data['pix']['expirationDate'])) {
            $expirationDate = $data['pix']['expirationDate'];
        }
        
        // Retornar formato compatível com o frontend
        echo json_encode([
            "success" => true,
            "pix" => [
                "qrcode" => $qrcode,
                "expirationDate" => $expirationDate
            ],
            "amount" => $data['amount'] ?? intval(round($valor * 100)),
            "id" => $data['id'] ?? null,
            "status" => $data['status'] ?? "WAITING_PAYMENT",
            "externalRef" => $data['externalRef'] ?? $externalRef
        ]);
    } else {
        // Erro na API
        $errorMsg = $responseData['message'] ?? "Erro ao gerar PIX";
        http_response_code($httpCode);
        echo json_encode([
            "success" => false,
            "error" => "api_error",
            "message" => $errorMsg,
            "httpCode" => $httpCode,
            "details" => $responseData
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "server_error",
        "message" => $e->getMessage()
    ]);
}
?>