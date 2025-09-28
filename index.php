<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Carrega o "autoload" do Composer para termos acesso à biblioteca.
// O caminho precisa subir um nível de pasta (de 'principal' para a raiz do projeto)

//require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/autoload.php';
// 2. Cria uma instância da biblioteca e aponta para a pasta RAIZ do projeto.
// A pasta raiz é um nível acima ('../') da pasta 'principal' onde este script está.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Responde que aceitamos a requisição POST de qualquer origem
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(204); // 204 No Content - Resposta padrão para pre-flight
    exit; // Interrompe o script aqui para a requisição OPTIONS
}



// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// --- CONFIGURAÇÃO DAS CHAVES DE API ---
// SUBSTITUA PELAS SUAS CHAVES REAIS!
$GROQ_API_KEY = $_ENV['GROQ_API_KEY'];      // COLE SUA CHAVE DA GROQ AQUI
$GEMINI_API_KEY = $_ENV['GEMINI_API_KEY']; // COLE SUA CHAVE DO GOOGLE AI STUDIO (GEMINI) AQUI


/**
 * Função para descrever uma imagem usando a API do Google Gemini Pro Vision.
 * @param string $base64Image Imagem codificada em Base64.
 * @param string $mimeType O tipo MIME da imagem (ex: 'image/jpeg').
 * @param string $apiKey Chave da API do Gemini.
 * @return array|null Retorna os dados da resposta ou null em caso de erro.
 */
function descreverImagemComGemini($base64Image, $mimeType, $apiKey) {
    // A chave da API é passada como um parâmetro na URL para o Gemini
    $apiUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
    
    $postData = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => 'Descreva detalhadamente o conteúdo desta imagem. Se houver texto, transcreva-o exatamente como aparece. Esta descrição será usada para criar um resumo.'
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $base64Image
                        ]
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

/**
 * Função para resumir um texto usando a API da Groq. (ESTA FUNÇÃO NÃO MUDA)
 * @param string $texto Texto a ser resumido.
 * @param string $apiKey Chave da API da Groq.
 * @return array|null Retorna os dados da resposta ou null em caso de erro.
 */
function resumirComGroq($texto, $apiKey) {
    $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    $postData = [
        'model' => 'openai/gpt-oss-20b', 
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Você é um assistente especialista em criar resumos concisos e claros em português.'
            ],
            [
                'role' => 'user',
                'content' => "Faça um resumo do seguinte texto:\n\n" . $texto
            ]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// --- LÓGICA PRINCIPAL ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    echo json_encode(['error' => 'Use o método POST.']);
    exit;
}

$textoParaResumir = '';

// Prioriza o envio de imagem
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    $caminhoArquivo = $_FILES['imagem']['tmp_name'];
    $tipoArquivo = mime_content_type($caminhoArquivo);
    
    if (in_array($tipoArquivo, ['image/jpeg', 'image/png'])) {
        $conteudoImagem = file_get_contents($caminhoArquivo);
        $base64Image = base64_encode($conteudoImagem);
        
        // **MUDANÇA AQUI: Chamando a nova função do Gemini**
        $respostaGemini = descreverImagemComGemini($base64Image, $tipoArquivo, $GEMINI_API_KEY);

        // **MUDANÇA AQUI: O caminho para o texto na resposta do Gemini é diferente**
        if (isset($respostaGemini['candidates'][0]['content']['parts'][0]['text'])) {
            $textoParaResumir = $respostaGemini['candidates'][0]['content']['parts'][0]['text'];
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Não foi possível analisar a imagem com o Gemini.', 'details' => $respostaGemini['error']['message'] ?? 'Erro desconhecido da API de visão.']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de imagem inválido. Use JPG ou PNG.']);
        exit;
    }
} 
// Se não houver imagem, usa o texto
elseif (!empty($_POST['texto'])) {
    $textoParaResumir = $_POST['texto'];
}

// Se não tivermos nem texto nem imagem, retorna erro
if (empty($textoParaResumir)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum texto ou imagem válida foi enviado.']);
    exit;
}

// Envia o texto para a Groq para resumir (NENHUMA MUDANÇA A PARTIR DAQUI)
$respostaGroq = resumirComGroq($textoParaResumir, $GROQ_API_KEY);

if (isset($respostaGroq['choices'][0]['message']['content'])) {
    $resumoFinal = $respostaGroq['choices'][0]['message']['content'];
    echo json_encode(['resumo' => $resumoFinal]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível gerar o resumo com a Groq.', 'details' => $respostaGroq['error']['message'] ?? 'Erro desconhecido da API da Groq.']);
    exit;
}


?>
