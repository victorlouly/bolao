<?php
// Desabilitar exibição de erros HTML
ini_set('display_errors', 0);
error_reporting(0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Carregar variáveis de ambiente (prioridade) ou do arquivo keys.env
    $checkifyKey = getenv("CHECKIFY_KEY");
    
    // Se não estiver nas variáveis de ambiente, tentar ler do arquivo keys.env
    if (!$checkifyKey || $checkifyKey === 'your_checkify_key_here') {
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
            $checkifyKey = getenv("CHECKIFY_KEY");
        }
    }

    if (!$checkifyKey || $checkifyKey === 'your_checkify_key_here') {
        throw new Exception("Chave CHECKIFY_KEY não configurada no arquivo keys.env");
    }

    // Obter CPF da URL
    $cpf = isset($_GET['cpf']) ? preg_replace('/\D/', '', $_GET['cpf']) : '';

    if (empty($cpf)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "invalid_cpf",
            "message" => "CPF não fornecido"
        ]);
        exit;
    }

    if (strlen($cpf) != 11) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "invalid_cpf",
            "message" => "CPF deve ter 11 dígitos"
        ]);
        exit;
    }

    // Verificar se cURL está disponível
    if (!function_exists('curl_init')) {
        throw new Exception("Extensão cURL não está instalada no servidor");
    }

    // Fazer requisição para a API Checkify
    $url = "https://api.checkify.space/api/v1/consultas/cpf/" . $cpf;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "checkify-key: " . $checkifyKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Erro cURL: " . $curlError);
    }

    if ($httpCode === 0) {
        throw new Exception("Não foi possível conectar à API Checkify");
    }

    // Processar resposta
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Resposta inválida da API Checkify");
    }

    // Verificar se houve erro 400 (CPF inválido)
    if ($httpCode == 400 && isset($data['message']) && $data['message'] === 'CPF inválido') {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "invalid_cpf",
            "message" => "CPF inválido"
        ]);
        exit;
    }

    // Verificar se o status é "success" e tem resultado
    if ($httpCode == 200 && isset($data['status']) && $data['status'] === 'success' && isset($data['resultado']['dados'])) {
        $dados = $data['resultado']['dados'];
        
        // Formatar data de nascimento se existir
        $dataNascimento = '';
        if (isset($dados['NASC']) && !empty($dados['NASC'])) {
            $dataNasc = new DateTime($dados['NASC']);
            $dataNascimento = $dataNasc->format('Y-m-d');
        }
        
        // Extrair primeiro nome
        $primeiroNome = '';
        if (isset($dados['NOME']) && !empty($dados['NOME'])) {
            $nomes = explode(' ', $dados['NOME']);
            $primeiroNome = $nomes[0] ?? '';
        }
        
        // Retornar dados formatados
        echo json_encode([
            "success" => true,
            "dadosBasicos" => [
                "nome" => $dados['NOME'] ?? '',
                "primeiroNome" => $primeiroNome,
                "cpf" => $dados['CPF'] ?? $cpf,
                "dataNascimento" => $dataNascimento,
                "sexo" => $dados['SEXO'] ?? '',
                "nomeMae" => $dados['NOME_MAE'] ?? '',
                "nomePai" => $dados['NOME_PAI'] ?? '',
                "renda" => $dados['RENDA'] ?? '',
                "rg" => $dados['RG'] ?? ''
            ],
            "telefones" => $data['resultado']['telefones'] ?? [],
            "enderecos" => $data['resultado']['enderecos'] ?? [],
            "emails" => $data['resultado']['emails'] ?? [],
            "parentes" => $data['resultado']['parentes'] ?? []
        ]);
    } elseif (isset($data['status']) && $data['status'] === 'not_found') {
        // CPF não encontrado
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "not_found",
            "message" => "CPF não encontrado na base de dados"
        ]);
    } elseif ($httpCode == 401) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "error" => "unauthorized",
            "message" => "Chave da API Checkify inválida ou expirada"
        ]);
    } else {
        // Outros erros
        http_response_code($httpCode);
        echo json_encode([
            "success" => false,
            "error" => "api_error",
            "message" => isset($data['message']) ? $data['message'] : "Erro ao consultar CPF",
            "httpCode" => $httpCode,
            "details" => $data
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