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
    country TEXT, 
    city TEXT, 
    isp TEXT, 
    user_agent TEXT, 
    screen_res TEXT, 
    timezone TEXT, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

function get_real_ip() {
    if (!empty($_SERVER['HTTP_X-FORWARDED-FOR'])) {
        $ips = explode(',',$_SERVER['HTTP_X-FORWARDED-FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

function get_ip_details($ip) {
    if ($ip == '127.0.0.1' or $ip == '::1' or$ip == 'Unknown' or strpos($ip, '10.') === 0 or strpos($ip, '192.168.') === 0) {
        return ['country' => 'محلي', 'city' => 'محلي', 'isp' => 'شبكة خاصة'];
    }
    $json = @file_get_contents("http://ip-api.com/json/" . $ip);
    $data = json_decode($json, true);
    if ($data and isset($data['status']) and$data['status'] == 'success') {
        return [
            'country' => $data['country'] ?? 'غير معروف',
            'city' => $data['city'] ?? 'غير معروف',
            'isp' => $data['isp'] ?? 'غير معروف'
        ];
    }
    return ['country' => 'غير معروف', 'city' => 'غير معروف', 'isp' => 'غير معروف'];
}

// 1. إعادة التوجيه وتسجيل الـ IP
if (isset($_GET['r'])) {
    $code =$_GET['r'];
    $stmt =$db->prepare("SELECT target_url FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {$target = $link['target_url'];$ip = get_real_ip();
        $geo = get_ip_details($ip);
        $ua =$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $screen =$_GET['screen'] ?? 'N/A';
        $tz =$_GET['tz'] ?? 'N/A';

        $logStmt =$db->prepare("INSERT INTO logs (code, ip, country, city, isp, user_agent, screen_res, timezone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $logStmt->execute([$code,$ip, $geo['country'],$geo['city'], $geo['isp'],$ua, $screen,$tz]);

        header("Location: " . $target);
        exit();
    } else {
        die("الرابط غير موجود.");
    }
}

// 2. إنشاء رابط جديد باستخدام الدومين الفعلي ليعمل التتبع 100%
$generated_link = "";
$tracking_code = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['create'])) {
    $target_url = trim($_POST['target_url']);
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        $target_url = "https://" . $target_url;
    }

    $code = substr(md5(uniqid(rand(), true)), 0, 7);
    $stmt =$db->prepare("INSERT INTO links (code, target_url) VALUES (?, ?)");
    $stmt->execute([$code,$target_url]);

    $tracking_code =$code;
    $domain = $_SERVER['HTTP_HOST'];$generated_link = "https://" . $domain . "/?r=" . $code;
}

$total_links =$db->query("SELECT COUNT(*) FROM links")->fetchColumn();
$total_visits =$db->query("SELECT COUNT(*) FROM logs")->fetchColumn();

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
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background-color: #0b0f19; color: #f3f4f6; padding: 20px; line-height: 1.5; }
        .container { max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: 14px; padding: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
        .header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        h1 { color: #38bdf8; font-size: 26px; font-weight: 800; }
        .stats-group { display: flex; gap: 12px; }
        .stat-box { background: #1f2937; padding: 10px 18px; border-radius: 10px; text-align: center; border: 1px solid #374151; }
        .stat-box span { display: block; font-size: 11px; color: #9ca3af; }
        .stat-box strong { font-size: 18px; color: #38bdf8; }
        h2 { color: #38bdf8; font-size: 18px; margin-bottom: 15px; font-weight: 700; }
        form { display: flex; gap: 10px; flex-wrap: wrap; }
        input[type="text"] { flex: 1; min-width: 250px; background: #030712; border: 1px solid #374151; border-radius: 10px; padding: 12px 16px; color: #fff; font-size: 14px; outline: none; }
        input[type="text"]:focus { border-color: #38bdf8; }
        button, .btn { background: #0284c7; color: white; border: none; padding: 12px 22px; border-radius: 10px; font-weight: bold; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; text-align: center; font-size: 14px; }
        button:hover, .btn:hover { background: #0369a1; }
        .result-box { background: #030712; border: 1px solid rgba(56, 189, 248, 0.3); padding: 16px; border-radius: 10px; margin-top: 15px; word-break: break-all; }
        .table-container { overflow-x: auto; margin-top: 15px; border: 1px solid #1f2937; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; text-align: right; font-size: 13px; }
        th { background: #030712; color: #9ca3af; padding: 12px; border-bottom: 1px solid #1f2937; }
        td { padding: 12px; border-bottom: 1px solid #1f2937; color: #e5e7eb; white-space: nowrap; }
        tr:hover { background: rgba(31, 41, 55, 0.4); }
        .badge { background: #064e3b; color: #34d399; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; border: 1px solid #065f46; }
        .ip-text { font-family: monospace; color: #38bdf8; font-weight: bold; }
        .copy-btn { background: #374151; padding: 8px 14px; font-size: 12px; }
        .copy-btn.success { background: #059669; }
        .link-row { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
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

        function copyText(text, btnElement) {
            navigator.clipboard.writeText(text).then(() => {
                const original = btnElement.innerText;
                btnElement.innerText = "تم النسخ ✓";
                btnElement.classList.add("success");
                setTimeout(() => {
                    btnElement.innerText = original;
                    btnElement.classList.remove("success");
                }, 2000);
            });
        }
    </script>
</head>
<body>
    <div class="container">
        
        <!-- Header -->
        <div class="card header">
            <div>
                <h1>Hello World from azez</h1>
                <p style="color: #9ca3af; font-size: 13px; margin-top: 4px;">لوحة تحكم تتبع الروابط المتقدمة</p>
            </div>
            <div class="stats-group">
                <div class="stat-box">
                    <span>الروابط</span>
                    <strong><?php echo $total_links; ?></strong>
                </div>
                <div class="stat-box">
                    <span>النقرات</span>
                    <strong style="color: #34d399;"><?php echo $total_visits; ?></strong>
                </div>
            </div>
        </div>

        <!-- Create Link -->
        <div class="card">
            <h2>🔗 إنشاء رابط تتبع جديد</h2>
            <form method="POST">
                <input type="text" name="target_url" placeholder="أدخل الرابط المستهدف (مثال: https://youtube.com)" required
