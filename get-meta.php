<?php
// get-meta.php — Ambil metadata (judul, penulis) dari satu atau semua EPUB
// Usage: get-meta.php              → semua buku
//        get-meta.php?book=books/x → satu buku

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

function extractMeta(string $epubPath): array {
    $default = [
        'file'   => $epubPath,
        'title'  => basename($epubPath, '.epub'),
        'author' => '',
    ];

    $zip = new ZipArchive;
    if ($zip->open($epubPath) !== true) return $default;

    // Baca container.xml
    $container = $zip->getFromName('META-INF/container.xml');
    if (!$container) { $zip->close(); return $default; }

    $dom = new DOMDocument;
    if (!@$dom->loadXML($container)) { $zip->close(); return $default; }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');
    $rootfiles = $xpath->query('//c:rootfiles/c:rootfile');
    if ($rootfiles->length === 0) { $zip->close(); return $default; }

    $opfPath = $rootfiles->item(0)->getAttribute('full-path');
    $opf = $zip->getFromName($opfPath);
    $zip->close();

    if (!$opf) return $default;

    $opfDom = new DOMDocument;
    if (!@$opfDom->loadXML($opf)) return $default;

    $xo = new DOMXPath($opfDom);
    $xo->registerNamespace('dc',  'http://purl.org/dc/elements/1.1/');
    $xo->registerNamespace('opf', 'http://www.idpf.org/2007/opf');

    $titleNode  = $xo->query('//dc:title');
    $authorNode = $xo->query('//dc:creator');

    $title  = $titleNode->length  ? trim($titleNode->item(0)->nodeValue)  : $default['title'];
    $author = $authorNode->length ? trim($authorNode->item(0)->nodeValue) : '';

    if ($title === '') $title = $default['title'];

    return [
        'file'   => $epubPath,
        'title'  => $title,
        'author' => $author,
    ];
}

// ── Single book ──
if (!empty($_GET['book'])) {
    $book = $_GET['book'];
    if (!preg_match('/^books\/[^\/]+\.epub$/', $book) || !file_exists($book)) {
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
        exit;
    }
    echo json_encode(extractMeta($book));
    exit;
}

// ── All books ──
$books = glob('books/*.epub') ?: [];

// Cache sederhana: kalau daftar buku tidak berubah, kembalikan cache
$cacheFile = 'cache/meta-cache.json';
$cacheValid = false;

if (file_exists($cacheFile)) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['_ts'])) {
        // Cek apakah ada buku baru atau dihapus
        $cachedFiles = array_column(
            array_filter($cached['books'], fn($b) => isset($b['file'])),
            'file'
        );
        sort($books); sort($cachedFiles);
        if ($books === $cachedFiles) {
            // Cek apakah ada buku yang dimodifikasi setelah cache dibuat
            $allFresh = true;
            foreach ($books as $b) {
                if (filemtime($b) > $cached['_ts']) { $allFresh = false; break; }
            }
            if ($allFresh) $cacheValid = true;
        }
    }
}

if ($cacheValid) {
    echo json_encode($cached['books']);
    exit;
}

// Generate metadata untuk semua buku
$result = array_map('extractMeta', $books);

// Simpan cache
$cacheDir = 'cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
file_put_contents($cacheFile, json_encode([
    '_ts'   => time(),
    'books' => $result,
]));

echo json_encode($result);