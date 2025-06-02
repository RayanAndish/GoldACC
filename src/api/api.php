<?php
// 1. بارگذاری Autoloader و کلاس‌های مورد نیاز
require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use App\Core\Database;
use App\Utils\Helper;

// 2. بارگذاری تنظیمات پروژه از فایل config
$config = require __DIR__ . '/../config/config.php';

// 3. ساخت Logger با Monolog و Handler بر اساس config
$logger = new Logger('api_handshake'); // نام کانال لاگ را می‌توان خاص‌تر انتخاب کرد
// مسیر فایل لاگ از config خوانده می‌شود، اگر وجود نداشت یک پیش‌فرض استفاده می‌شود.
$logFile = $config['log']['file'] ?? (__DIR__ . '/../logs/api_handshake.log');
// سطح لاگ از config خوانده می‌شود، اگر وجود نداشت DEBUG استفاده می‌شود.
$logLevel = Logger::toMonologLevel($config['log']['level'] ?? 'DEBUG');
$logger->pushHandler(new StreamHandler($logFile, $logLevel));

// 4. مقداردهی Helper (اگر برای لاگ یا تنظیمات عمومی پروژه استفاده می‌شود)
// این خط فرض می‌کند که کلاس Helper شما یک متد استاتیک initialize دارد.
Helper::initialize($logger, $config);

// 5. مقداردهی Singleton Database و دریافت PDO
// این خط فرض می‌کند که کلاس Database شما یک متد استاتیک getInstance و سپس متد getConnection دارد.
// و همچنین config و logger را به عنوان آرگومان می‌پذیرد.
$db = Database::getInstance($config['database'], $logger)->getConnection();

$settingsRepo = new SettingsRepository($db, $logger);
$securityService = new SecurityService($logger, $config);

// مقداردهی متغیرها از منابع واقعی
$licenseKey  = $settingsRepo->get('license_key');
$domain      = $settingsRepo->get('domain');
$ip          = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$rayId       = $_SESSION['ray_id'] ?? null;
$hardwareId  = $securityService->generateHardwareId();
$requestCode = $securityService->generateRequestCode($ip, $domain, $rayId);

$data = [
    'license_key'  => $licenseKey,
    'domain'       => $domain,
    'ip'           => $ip,
    'ray_id'       => $rayId,
    'hardware_id'  => $hardwareId,
    'request_code' => $requestCode,
];

// ارسال درخواست handshake به سرور
$ch = curl_init('https://goldacc.ir/api/handshake');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if (isset($result['handshake_string']) && isset($result['handshake_map'])) {
    $settingsRepo->set('api_handshake_string', $result['handshake_string']);
    $settingsRepo->set('api_handshake_map', $result['handshake_map']);
    $settingsRepo->set('api_handshake_expires', time() + ($result['expires_in'] ?? 3600));
}

// استخراج api_key, api_secret, hmac_salt از handshake
$handshakeString = $settingsRepo->get('api_handshake_string');
$mappingJson = base64_decode($settingsRepo->get('api_handshake_map'));
$mapping = json_decode($mappingJson, true);

$api_key    = substr($handshakeString, $mapping['api_key'][0], $mapping['api_key'][1] - $mapping['api_key'][0] + 1);
$api_secret = substr($handshakeString, $mapping['api_secret'][0], $mapping['api_secret'][1] - $mapping['api_secret'][0] + 1);
$hmac_salt  = substr($handshakeString, $mapping['hmac_salt'][0], $mapping['hmac_salt'][1] - $mapping['hmac_salt'][0] + 1);

// ساخت signature برای درخواست‌های بعدی
$body = json_encode($data, JSON_UNESCAPED_UNICODE);
$timestamp = time();
$nonce = bin2hex(random_bytes(16));
$signature = hash_hmac('sha3-512', $body . $nonce . $timestamp, $api_secret . $hmac_salt);

// حالا می‌توانی این headerها را برای درخواست‌های بعدی استفاده کنی
$headers = [
    'X-API-KEY: ' . $api_key,
    'X-NONCE: ' . $nonce,
    'X-TIMESTAMP: ' . $timestamp,
    'X-SIGNATURE: ' . $signature,
    'Content-Type: application/json'
];

// نمونه ارسال درخواست امن (در صورت نیاز)
// $ch = curl_init('https://goldacc.ir/api/secure-endpoint');
// curl_setopt($ch, CURLOPT_POST, 1);
// curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
// curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// $secureResponse = curl_exec($ch);
// curl_close($ch);

echo json_encode(['status' => 'ok']);