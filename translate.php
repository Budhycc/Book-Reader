<?php
// translate.php — Proxy tipis ke deep-translator API
// Endpoint publik: https://deep-translator-api.azurewebsites.net/translate
// Dokumentasi Swagger: https://deep-translator-api.azurewebsites.net/

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

/* ── Kirim ke deep-translator API ── */
$apiUrl  = 'https://deep-translator-api.azurewebsites.net/translate';
$payload = json_encode([
    'text'        => $text,
    'source'      => $source,
    'target'      => $target,
    'translator'  => 'google',   // google translator (gratis, tanpa API key)
]);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content'       => $payload,
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

/* ── Fallback: coba endpoint alternatif jika gagal ── */
if ($response === false || $httpCode >= 500) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'API tidak merespons. Coba lagi.']);
    exit;
}

/* ── Teruskan respons API ── */
$data = json_decode($response, true);
if (!$data) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Respons API tidak valid']);
    exit;
}

// Normalisasi respons: pastikan field 'translation' ada
// deep-translator API mengembalikan { "translation": "..." }
if (isset($data['translation'])) {
    echo json_encode([
        'ok'          => true,
        'translation' => $data['translation'],
        'source'      => $source,
        'target'      => $target,
    ]);
} elseif (isset($data['translated_text'])) {
    echo json_encode([
        'ok'          => true,
        'translation' => $data['translated_text'],
        'source'      => $source,
        'target'      => $target,
    ]);
} elseif ($httpCode >= 400) {
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'error' => $data['detail'] ?? 'Terjemahan gagal']);
} else {
    // Teruskan apa adanya jika strukturnya berbeda
    echo $response;
}