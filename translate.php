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

$source = trim($body['source'] ?? 'auto');
$target = trim($body['target'] ?? 'id');

/* ── Terima single string atau array of strings ── */
$texts = [];
if (isset($body['texts']) && is_array($body['texts'])) {
    $texts = $body['texts'];
} elseif (isset($body['text']) && trim($body['text']) !== '') {
    $texts = [$body['text']];
}

if (empty($texts)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Teks kosong']);
    exit;
}

/* ── Bersihkan teks (trim space) ── */
$texts = array_map(function($t) { return trim($t); }, $texts);

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

/* ── Gabungkan teks dengan delimiter " ~||~ " ── */
// Google Translate dapat mempertahankan dan menerjemahkan delimiter ini menjadi dirinya sendiri
$mergedText = implode(" ~||~ ", $texts);

// Batasi max length (Google Translate gtx POST limit ~5000 chars)
if (mb_strlen($mergedText) > 5000) {
    $mergedText = mb_substr($mergedText, 0, 5000);
}

/* ── Kirim ke Google Translate API (Gunakan POST) ── */
$apiUrl = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . urlencode($source) . '&tl=' . urlencode($target) . '&dt=t';

$postData = http_build_query(['q' => $mergedText]);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n" .
                           "Accept: application/json\r\n",
        'content'       => $postData,
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

// Ekstrak terjemahan gabungan
$translation = '';
foreach ($data[0] as $part) {
    if (isset($part[0])) {
        $translation .= $part[0];
    }
}

// Split kembali menggunakan regex (menangani spasi tambahan jika ada)
$translatedArray = preg_split('/~\s*\|\|\s*~/', $translation);
$translatedArray = array_map(function($t) { return trim($t); }, $translatedArray);

// Jika jumlah tidak sama karena error split, fallback
if (count($translatedArray) < count($texts)) {
    // Pad dengan string kosong jika Google menghilangkan beberapa teks
    $translatedArray = array_pad($translatedArray, count($texts), '');
}

echo json_encode([
    'ok'           => true,
    'translations' => $translatedArray,
    'translation'  => $translatedArray[0] ?? '', // Untuk backward compatibility
    'source'       => $source,
    'target'       => $target,
]);