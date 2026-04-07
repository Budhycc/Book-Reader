<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');   // aman karena hanya dipakai oleh reader lokal

/* ── Hanya terima POST ── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── Baca body JSON ── */
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$text   = trim($body['text']   ?? '');
$source = trim($body['source'] ?? 'auto');
$target = trim($body['target'] ?? 'id');

if ($text === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Teks kosong']);
    exit;
}

// Batasi panjang teks (API biasanya punya batas sendiri, tapi aman dibatasi di sini)
if (mb_strlen($text) > 5000) {
    $text = mb_substr($text, 0, 5000);
}

/* ── Validasi bahasa target (whitelist sederhana) ── */
$allowed = [
    'id','en','ms','ar','zh-CN','zh-TW','ja','ko','fr','de','es','pt',
    'ru','nl','it','tr','th','vi','pl','hi','sv','da','fi','no','cs','ro',
    'hu','sk','bg','uk','hr','lt','lv','et','sl','sq','mk','auto'
];
if (!in_array($target, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bahasa target tidak diizinkan']);
    exit;
}
if ($source !== 'auto' && !in_array($source, $allowed, true)) {
    $source = 'auto';
}

/* ── Kirim ke Google Translate API ── */
$apiUrl = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . urlencode($source) . '&tl=' . urlencode($target) . '&dt=t&q=' . urlencode($text);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'header'        => "Accept: application/json\r\n",
        'timeout'       => 15,
        'ignore_errors' => true,
    ]
]);

$response = @file_get_contents($apiUrl, false, $ctx);
$httpCode = 0;
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) {
            $httpCode = (int)$m[1];
        }
    }
}

/* ── Fallback jika gagal ── */
if ($response === false || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'API tidak merespons. Coba lagi.']);
    exit;
}

/* ── Parse respons Google Translate ── */
$data = json_decode($response, true);
if (!$data || !isset($data[0][0][0])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Respons API tidak valid']);
    exit;
}

// Ekstrak terjemahan
$translation = '';
foreach ($data[0] as $part) {
    if (isset($part[0])) {
        $translation .= $part[0];
    }
}

echo json_encode([
    'ok'          => true,
    'translation' => $translation,
    'source'      => $source,
    'target'      => $target,
]);