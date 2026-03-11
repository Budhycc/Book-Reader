<?php
// get-epub-part.php
$book = $_GET['book'] ?? '';
$file = $_GET['file'] ?? '';

// Validasi book: hanya file .epub di dalam folder books/
if (!preg_match('/^books\/[^\/]+\.epub$/', $book) || !file_exists($book)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Book not found.');
}

// Validasi file: cegah path traversal dan absolute path
if (strpos($file, '..') !== false || strpos($file, '/') === 0) {
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('Invalid file path.');
}

// Dapatkan timestamp modifikasi file EPUB (sebagai dasar ETag)
$fileTimestamp = filemtime($book);
// Buat ETag berdasarkan path file di dalam EPUB dan timestamp buku
$etag = '"' . md5($file . $fileTimestamp) . '"';

// Kirim header ETag dan cek apakah browser sudah memiliki versi yang sama
header("ETag: $etag");
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit();
}

// Buka arsip EPUB (ZIP)
$zip = new ZipArchive;
if ($zip->open($book) !== true) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Cannot open EPUB archive.');
}

$content = $zip->getFromName($file);
$zip->close();

if ($content === false) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('File not found in EPUB.');
}

// Tentukan MIME type berdasarkan ekstensi
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimeTypes = [
    'xhtml' => 'application/xhtml+xml',
    'html'  => 'text/html',
    'htm'   => 'text/html',
    'css'   => 'text/css',
    'xml'   => 'application/xml',
    'opf'   => 'application/oebps-package+xml',
    'ncx'   => 'application/x-dtbncx+xml',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'png'   => 'image/png',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'ttf'   => 'font/ttf',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'otf'   => 'font/otf',
    'js'    => 'application/javascript',
    'json'  => 'application/json',
];
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Izinkan cache selama 1 tahun (atau sesuai kebutuhan)
header("Cache-Control: public, max-age=31536000, immutable");
header("Content-Type: $contentType");
echo $content;