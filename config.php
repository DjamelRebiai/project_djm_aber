<?php
// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'forest_tourism');

// إعدادات التطبيق
define('SITE_NAME', 'السياحة الغابية');
define('SITE_URL', 'http://localhost/forest-tourism');
define('DEFAULT_PROFILE', 'uploads/trip_5_1746209965.png');
define('UPLOADS_DIR', __DIR__.'/uploads/');
define('PROFILE_UPLOAD_DIR', __DIR__.'/uploads/user/');


// التحقق من وجود مجلدات التحميلات
$requiredDirs = [
    UPLOADS_DIR,
    PROFILE_UPLOAD_DIR
];

foreach ($requiredDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        // تغيير المالك للمجلد إذا كان الخادم يعمل تحت مستخدم مختلف
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            chown($dir, 'www-data'); // أو 'daemon' حسب إعداداتك
            chgrp($dir, 'www-data');
        }
    }
}

// اتصال قاعدة البيانات
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    error_log("فشل الاتصال بقاعدة البيانات: ".$e->getMessage());
    die("نظام الصيانة قيد التطوير. الرجاء المحاولة لاحقاً.");
}

// إعدادات الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'name' => 'ForestTourismSession',
        'cookie_lifetime' => 86400, // يوم واحد
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// التحقق من وجود مجلد التحميلات
if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}

?>