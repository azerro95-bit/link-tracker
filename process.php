<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'طلب غير صالح.']);
    exit;
}

if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'لم يتم استقبال التسجيل الصوتي.']);
    exit;
}

$selectedModel = $_POST['model'] ?? 'dz_female_young';

$uploadDir = __DIR__ . '/uploads/';
$outputDir = __DIR__ . '/outputs/';

if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
if (!file_exists($outputDir)) mkdir($outputDir, 0777, true);

$fileExtension = pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION);
$filename = uniqid('rec_') . '.' . ($fileExtension ?: 'webm');
$inputFilePath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['audio']['tmp_filename'] ?? $_FILES['audio']['tmp_name'], $inputFilePath)) {
    echo json_encode(['success' => false, 'error' => 'فشل حفظ الملف على السيرفر.']);
    exit;
}

$outputFilename = uniqid('vn_') . '.opus';
$outputFilePath = $outputDir . $outputFilename;

// تشغيل سكربت البايثون للمعالجة والتحويل
$command = sprintf(
    '/opt/venv/bin/python3 %s %s %s %s 2>&1',
    escapeshellarg(__DIR__ . '/convert_voice.py'),
    escapeshellarg($inputFilePath),
    escapeshellarg($outputFilePath),
    escapeshellarg($selectedModel)
);

$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

if ($returnVar === 0 && file_exists($outputFilePath)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
    $fileUrl = $baseUrl . '/outputs/' . $outputFilename;

    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl,
        'filename' => $outputFilename
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في معالجة الصوت.',
        'details' => implode("\n", $output)
    ]);
}
