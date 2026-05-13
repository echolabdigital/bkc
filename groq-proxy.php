<?php
// ── GROQ PROXY — Brazil Korea Conference ──
// Coloque este arquivo na raiz do site (public_html/)
// A chave fica 100% no servidor, nunca exposta no front-end.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

// ── CHAVE GROQ ──
$GROQ_API_KEY = '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';
$GROQ_MODEL   = 'llama-3.3-70b-versatile';
$GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

// ── INPUT ──
$body    = json_decode(file_get_contents('php://input'), true);
$mode    = isset($body['mode'])    ? trim($body['mode'])    : 'geral';
$message = isset($body['message']) ? trim($body['message']) : '';
$lang    = isset($body['lang'])    ? trim($body['lang'])    : 'pt';
if (!in_array($lang, ['pt', 'ko', 'en'])) $lang = 'pt';

if (empty($message)) {
    $empty_msgs = ['pt' => 'Por favor, escreva sua dúvida.', 'ko' => '질문을 입력해 주세요.', 'en' => 'Please write your question.'];
    echo json_encode(['reply' => $empty_msgs[$lang]]);
    exit;
}

// ── PROMPTS POR MODO E IDIOMA ──
$wa = 'wa.me/5511912717391';

$lang_instructions = [
    'pt' => 'Responda SEMPRE em Português do Brasil.',
    'ko' => '항상 한국어로 답변하세요.',
    'en' => 'Always respond in American English.',
];
$wa_ctas = [
    'pt' => ["Manda uma mensagem no WhatsApp: $wa", "Chama no WhatsApp: $wa", "Fala com a gente no WhatsApp: $wa"],
    'ko' => ["WhatsApp으로 메시지 보내세요: $wa", "WhatsApp으로 연락하세요: $wa"],
    'en' => ["Send us a message on WhatsApp: $wa", "Reach us on WhatsApp: $wa"],
];
$li  = $lang_instructions[$lang];
$wac = $wa_ctas[$lang][0];
$wac2 = $wa_ctas[$lang][count($wa_ctas[$lang]) - 1];

$prompts = [

  'geral' => "$li
Você é o assistente do Brazil Korea Conference (BKC), evento de negócios Brasil-Coreia com delegação oficial para Londres em setembro de 2026 (USD 3.990 por pessoa, vagas limitadas).

Regras absolutas:
- Responda em no máximo 3 frases curtas e diretas.
- Tom caloroso, executivo e animado — como alguém que realmente quer ajudar.
- Nunca use tópicos, listas ou markdown.
- Sempre termine convidando para o WhatsApp de forma natural, usando exatamente este link: $wac
- Se perguntar sobre preço, datas ou inscrição, confirme os dados e direcione para o WhatsApp.
- Gere curiosidade sobre a experiência em Londres.",

  'networking' => "$li
Você é o consultor de networking do Brazil Korea Conference (BKC 2026, Londres).

Regras absolutas:
- Com base no setor ou interesse do usuário, sugira em 1 frase com quem ele deveria se conectar no evento ou qual painel é ideal para ele.
- Seja específico, empolgante e breve — máximo 2 frases no total.
- Finalize sempre com: '$wac'
- Nunca use listas, tópicos ou markdown.",

  'cultural' => "$li
Você é o guia de etiqueta de negócios coreana do Brazil Korea Conference.

Regras absolutas:
- Se o usuário pedir uma frase em coreano: escreva em hangul, coloque a pronúncia romanizada entre parênteses, e dê 1 dica relâmpago de etiqueta.
- Se for uma dúvida cultural: responda em no máximo 2 frases objetivas.
- Finalize sempre com: '$wac2'
- Nunca use listas, tópicos ou markdown."

];

$system = isset($prompts[$mode]) ? $prompts[$mode] : $prompts['geral'];

// ── CHAMADA GROQ ──
$payload = json_encode([
  'model'       => $GROQ_MODEL,
  'max_tokens'  => 180,
  'temperature' => 0.7,
  'messages'    => [
    ['role' => 'system', 'content' => $system],
    ['role' => 'user',   'content' => $message]
  ]
]);

$ch = curl_init($GROQ_ENDPOINT);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $payload,
  CURLOPT_HTTPHEADER     => [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $GROQ_API_KEY
  ],
  CURLOPT_TIMEOUT        => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['reply' => 'Erro ao conectar com a IA. Tente novamente ou fale direto no WhatsApp: ' . $wa]);
    exit;
}

$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? 'Sem resposta.';

echo json_encode(['reply' => trim($reply)]);
