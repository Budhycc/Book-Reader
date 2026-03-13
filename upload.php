<?php
// upload.php — endpoint untuk upload file EPUB
header('Content-Type: application/json');

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$uploadDir = __DIR__ . '/books/';

// Pastikan folder books/ ada
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Gagal membuat folder books/']);
        exit;
    }
}

if (empty($_FILES['epub'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tidak ada file yang dikirim']);
    exit;
}

$file = $_FILES['epub'];

// Cek error upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (php.ini)',
        UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar (form)',
        UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temp tidak ditemukan',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file',
        UPLOAD_ERR_EXTENSION  => 'Diblokir oleh ekstensi PHP',
    ];
    $msg = $errors[$file['error']] ?? 'Error upload tidak diketahui';
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// Validasi ekstensi
$originalName = $file['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($ext !== 'epub') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Hanya file .epub yang diizinkan']);
    exit;
}

// Sanitasi nama file
$safeName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', pathinfo($originalName, PATHINFO_FILENAME));
$safeName = trim($safeName) ?: 'book_' . time();
$destName = $safeName . '.epub';
$destPath = $uploadDir . $destName;

// Jika nama sudah ada, tambah suffix
$counter = 1;
while (file_exists($destPath)) {
    $destName = $safeName . '_' . $counter . '.epub';
    $destPath = $uploadDir . $destName;
    $counter++;
}

// Pindahkan file
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal menyimpan file']);
    exit;
}

echo json_encode(['ok' => true, 'filename' => $destName]);