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
    lat TEXT, 
    lon TEXT, 
    isp TEXT, 
    device_info TEXT, 
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

function parse_isp($isp_name, $org_name) {$combined = $isp_name . ' ' .$org_name;
    if (stripos($combined, 'Mobilis') !== false or stripos($combined, 'ATM') !== false or stripos($combined, 'Telecom Mobilis') !== false) return 'موبيليس (Mobilis)';
    if (stripos($combined, 'Djezzy') !== false or stripos($combined, 'Optimum Telecom') !== false) return 'جازي (Djezzy)';
    if (stripos($combined, 'Ooredoo') !== false or stripos($combined, 'Wataniya') !== false) return 'أوريدو (Ooredoo)';
    if (stripos($combined, 'Algérie Télécom') !== false or stripos($combined, 'Algerie Telecom') !== false or stripos($combined, 'AT') !== false) return 'اتصالات الجزائر (Algérie Télécom)';
    return trim($isp_name) ?: 'غير معروف';
}

function get_device_type($ua) {
    if (stripos($ua, 'Samsung') !== false)$brand = 'سامسونج (Samsung)';
    elseif (stripos($ua, 'iPhone') !== false)$brand = 'آيفون (iPhone)';
    elseif (stripos($ua, 'Huawei') !== false)$brand = 'هواوي (Huawei)';
    elseif (stripos($ua, 'Xiaomi') !== false or stripos($ua, 'Redmi') !== false)$brand = 'شاومي (Xiaomi)';
    elseif (stripos($ua, 'Oppo') !== false)$brand = 'أوبو (Oppo)';
    elseif (stripos($ua, 'Vivo') !== false)$brand = 'فيفو (Vivo)';
    elseif (stripos($ua, 'Realme') !== false)$brand = 'ريلمي (Realme)';
    else $brand = 'جهاز ذكي';

    if (stripos($ua, 'Android') !== false) {
        return $brand . ' - أندرويد';
    } elseif (stripos($ua, 'iPhone') !== false or stripos($ua, 'iPad') !== false) {
        return $brand . ' - آي أو إس';
    } elseif (stripos($ua, 'Windows') !== false) {
        return 'حاسوب - ويندوز';
    } elseif (stripos($ua, 'Macintosh') !== false or stripos($ua, 'Mac OS') !== false) {
        return 'حاسوب - ماك';
    } elseif (stripos($ua, 'Linux') !== false) {
        return 'نظام لينكس';
    }
    return 'جهاز غير معروف';
}

function get_ip_details($ip) {
    if ($ip == '127.0.0.1' or $ip == '::1' or$ip == 'Unknown' or strpos($ip, '10.') === 0 or strpos($ip, '192.168.') === 0) {
        return ['country' => 'الجزائر', 'city' => 'محلي', 'lat' => '36.7538', 'lon' => '3.0588', 'isp' => 'شبكة محلية'];
    }
    $json = @file_get_contents("http://ip-api.com/json/" . $ip . "?fields=status,country,city,lat,lon,isp,org");
    $data = json_decode($json, true);
    if ($data and isset($data['status']) and$data['status'] == 'success') {
        return [
            'country' => $data['country'] ?? 'غير معروف',
            'city' => $data['city'] ?? 'غير معروف',
            'lat' => $data['lat'] ?? '36.7538',             'lon' =>$data['lon'] ?? '3.0588',
            'isp' => parse_isp($data['isp'] ?? '',$data['org'] ?? '')
        ];
    }
    return ['country' => 'غير معروف', 'city' => 'غير معروف', 'lat' => '36.7538', 'lon' => '3.0588', 'isp' => 'غير معروف'];
}

if (isset($_GET['r'])) {
    $code =$_GET['r'];
    $stmt =$db->prepare("SELECT target_url FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {$target = $link['target_url'];$ip = get_real_ip();
        $geo = get_ip_details($ip);
        $ua =$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $device = get_device_type($ua);
        $screen =$_GET['screen'] ?? 'غير متوفر';
        $tz =$_GET['tz'] ?? 'غير متوفر';

        $logStmt =$db->prepare("INSERT INTO logs (code, ip, country, city, lat, lon, isp, device_info, screen_res, timezone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $logStmt->execute([$code,$ip, $geo['country'],$geo['city'], $geo['lat'],$geo['lon'], $geo['isp'],$device, $screen,$tz]);

        header("Location: " . $target);
        exit();
    } else
