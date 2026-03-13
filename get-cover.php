<?php
// get-cover.php
$book = $_GET['book'] ?? '';

// Validasi book: hanya file .epub di dalam folder books/
if (!preg_match('/^books\/[^\/]+\.epub$/', $book) || !file_exists($book)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Book not found.');
}

// ── DISK CACHE: serve langsung jika sudah pernah di-resize ──
$cacheDir  = __DIR__ . '/cache/covers/';
$cacheKey  = md5($book . filemtime($book));
$cachePath = $cacheDir . $cacheKey . '.jpg';

if (file_exists($cachePath)) {
    $etag = '"' . $cacheKey . '"';
    header("ETag: $etag");
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit();
    }
    header("Cache-Control: public, max-age=31536000, immutable");
    header("Content-Type: image/jpeg");
    header("Content-Length: " . filesize($cachePath));
    readfile($cachePath);

    // ── AUTO-CLEANUP (1% chance): hapus cache orphan buku yang sudah dihapus ──
    if (rand(1, 100) === 1 && is_dir($cacheDir)) {
        $validKeys = [];
        foreach (glob(__DIR__ . '/books/*.epub') as $epub) {
            $validKeys[md5('books/' . basename($epub) . filemtime($epub)) . '.jpg'] = true;
        }
        foreach (glob($cacheDir . '*.jpg') as $cached) {
            if (!isset($validKeys[basename($cached)])) {
                @unlink($cached);
            }
        }
    }

    exit();
}

// Buka arsip EPUB (ZIP)
$zip = new ZipArchive;
if ($zip->open($book) !== true) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Cannot open EPUB archive.');
}

// Baca container.xml untuk menemukan path file OPF
$container = $zip->getFromName('META-INF/container.xml');
if (!$container) {
    $zip->close();
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Invalid EPUB: missing container.xml');
}

// Parse container.xml dengan DOMDocument
$dom = new DOMDocument();
if (!$dom->loadXML($container)) {
    $zip->close();
    http_response_code(500);
    exit('Failed to parse container.xml');
}

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');
$rootfiles = $xpath->query('//c:rootfiles/c:rootfile');
if ($rootfiles->length === 0) {
    $zip->close();
    http_response_code(500);
    exit('No rootfile found in container.xml');
}
$opfPath = $rootfiles->item(0)->getAttribute('full-path');

// Baca file OPF
$opf = $zip->getFromName($opfPath);
if (!$opf) {
    $zip->close();
    http_response_code(404);
    exit('OPF file not found');
}

// Cari cover image dalam OPF
$opfDom = new DOMDocument();
if (!$opfDom->loadXML($opf)) {
    $zip->close();
    http_response_code(500);
    exit('Failed to parse OPF');
}
$xpathOpf = new DOMXPath($opfDom);
$xpathOpf->registerNamespace('opf', 'http://www.idpf.org/2007/opf');

// Metode 1: Cari manifest item dengan properties='cover-image'
$coverItem = $xpathOpf->query('//opf:manifest/opf:item[@properties="cover-image"]');
if ($coverItem->length === 0) {
    // Metode 2: Cari meta name='cover'
    $coverId = null;
    $meta = $xpathOpf->query('//opf:meta[@name="cover"]');
    if ($meta->length > 0) {
        $coverId = $meta->item(0)->getAttribute('content');
        $coverItem = $xpathOpf->query('//opf:manifest/opf:item[@id="' . $coverId . '"]');
    }
}

if ($coverItem->length === 0) {
    $zip->close();
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Cover not found');
}

$coverHref = $coverItem->item(0)->getAttribute('href');
// Resolve path relatif terhadap OPF
$baseDir = dirname($opfPath) . '/';
if ($baseDir === './') $baseDir = '';
$coverPath = $baseDir . $coverHref;
$coverPath = str_replace('//', '/', $coverPath);

// Ambil file gambar dari ZIP
$coverData = $zip->getFromName($coverPath);
$zip->close();

if (!$coverData) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Cover file not found in EPUB');
}

// ─────────────────────────────────────────────────────────────
// KOMPRESI & RESIZE DI SERVER (menggunakan GD)
// Target: max 300px lebar, JPEG quality 75
// ─────────────────────────────────────────────────────────────
$MAX_WIDTH   = 300;   // lebar maksimum output (px)
$JPEG_QUALITY = 30;   // kualitas JPEG (0–100)

$compressed = false;

if (extension_loaded('gd')) {
    $src = @imagecreatefromstring($coverData);
    if ($src !== false) {
        $origW = imagesx($src);
        $origH = imagesy($src);

        // Hitung dimensi baru jika perlu di-resize
        if ($origW > $MAX_WIDTH) {
            $newW = $MAX_WIDTH;
            $newH = (int) round($origH * $MAX_WIDTH / $origW);
        } else {
            $newW = $origW;
            $newH = $origH;
        }

        $dst = imagecreatetruecolor($newW, $newH);

        // Pertahankan transparansi untuk PNG
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        imagealphablending($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        // Tangkap output JPEG ke buffer
        ob_start();
        imagejpeg($dst, null, $JPEG_QUALITY);
        $compressed = ob_get_clean();


    }
}

// Gunakan data terkompresi jika berhasil, fallback ke asli jika GD gagal
if ($compressed !== false && $compressed !== '') {
    $outputData    = $compressed;
    $outputMime    = 'image/jpeg';

    // Simpan ke disk cache untuk request berikutnya
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    file_put_contents($cachePath, $outputData);
} else {
    // Fallback: kirim gambar asli tanpa kompres
    $outputData    = $coverData;
    $ext = strtolower(pathinfo($coverHref, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
    ];
    $outputMime = $mimeTypes[$ext] ?? 'image/jpeg';
}

// Kirim header cache (1 tahun) dan ETag
$etag = '"' . md5($outputData) . '"';
header("ETag: $etag");
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit();
}
header("Cache-Control: public, max-age=31536000, immutable");
header("Content-Type: $outputMime");
header("Content-Length: " . strlen($outputData));
echo $outputData;