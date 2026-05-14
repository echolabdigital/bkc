<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['decision'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$decision = in_array($body['decision'], ['accepted', 'declined']) ? $body['decision'] : 'unknown';
$lang     = in_array($body['lang'] ?? '', ['pt', 'ko', 'en']) ? $body['lang'] : 'pt';
$ts       = date('c');

// Anonymize IP: mask last octet for IPv4, last group for IPv6
$ip_raw = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip     = strtok($ip_raw, ',');
$ip     = filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : 'unknown';
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $ip = preg_replace('/\.\d+$/', '.0', $ip);
} elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $ip = preg_replace('/:[^:]+$/', ':0', $ip);
}

$record = [
    'ts'       => $ts,
    'decision' => $decision,
    'lang'     => $lang,
    'ip'       => $ip,
    'ua'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
];

$dir  = __DIR__ . '/data/lgpd';
$file = $dir . '/consents.jsonl';

if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

file_put_contents($file, json_encode($record) . "\n", FILE_APPEND | LOCK_EX);

http_response_code(204);
