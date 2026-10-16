<?php
header('Content-Type: application/json; charset=utf-8');

// السماح بالأخطاء لتتبع المشكلة إن وجدت
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'طلب غير صالح.']);
    exit;
}

if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'لم يتم استقبال التسجيل الصوتي أو حدث خطأ في الرفع.']);
    exit;
}

$selectedModel = $_POST['model'] ?? 'dz_female_young';

$uploadDir = __DIR__ . '/uploads/';
$outputDir = __DIR__ . '/outputs/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$fileExtension = pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION);
$filename = uniqid('rec_') . '.' . ($fileExtension ?: 'webm');
$inputFilePath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['audio']['tmp_name'], $inputFilePath)) {
    echo json_encode(['success' => false, 'error' => 'فشل حفظ الملف على السيرفر.']);
    exit;
}

$outputFilename = uniqid('vn_') . '.opus';
$outputFilePath = $outputDir . $outputFilename;

$pythonScript = __DIR__ . '/convert_voice.py';
if (!file_exists($pythonScript)) {
    echo json_encode(['success' => false, 'error' => 'ملف بايثون convert_voice.py غير موجود على السيرفر!']);
    exit;
}

// تنفيذ سكربت بايثون
$command = sprintf(
    '/opt/venv/bin/python3 %s %s %s %s 2>&1',
    escapeshellarg($pythonScript),
    escapeshellarg($inputFilePath),
    escapeshellarg($outputFilePath),
    escapeshellarg($selectedModel)
);

$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

// تنظيف الملف المؤقت المدخل
if (file_exists($inputFilePath)) {
    @unlink($inputFilePath);
}

if ($returnVar === 0 && file_exists($outputFilePath)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
    $fileUrl = $baseUrl . '/outputs/' . $outputFilename;

    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'فشل تنفيذ سكربت المعالجة.',
        'details' => implode(" | ", $output)
    ]);
}
