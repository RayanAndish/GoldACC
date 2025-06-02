<?php

/**
 * Script to build a full update package (.zip) and latest.json file.
 * Does NOT rely on Git. Includes all project files except exclusions.
 *
 * Usage: php UpdateServer/build_update.php <new_version>
 * 
 * Example: php UpdateServer/build_update.php 1.1.0
 *
 * Requires php_zip extension.
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// --- Configuration ---
$projectRoot = dirname(__DIR__); // Assumes this script is in UpdateServer/
$outputDir = $projectRoot . '/UpdateServer/dist';
// $migrationsDirRelative = 'database/migrations'; // دیگر به طور خاص استفاده نمی‌شود، چون همه چیز کپی می‌شود
$zipFileNameFormat = 'update-v%s.zip';
$latestJsonFileName = 'latest.json';
$publicBaseUrlForDownloads = 'https://goldacc.ir/downloads'; // <<--- این را با URL عمومی خودتان تنظیم کنید

$filesToExclude = [
    // Files/Patterns to always exclude from the update package
    '.git', // اگر پوشه .git وجود دارد، همچنان بهتر است exclude شود
    '.gitignore',
    '.gitattributes',
    'UpdateServer/dist/', // پوشه خروجی خود اسکریپت
    'UpdateServer/build_update.php', // خود این اسکریپت
    // 'composer.lock', // بسته به نیاز
    // 'phinx.php',
    '*.log',
    'logs/', // پوشه لاگ‌های برنامه کلاینت (اگر در روت پروژه است)
    'backups/',
    '.env',
    'vendor/bin/', // معمولاً نیاز نیست
    'node_modules/', // اگر از npm استفاده می‌کنید
    // فایل‌های مربوط به محیط توسعه خاص شما
    // '.idea/',
    // '.vscode/',
    // '.DS_Store',
    // 'Thumbs.db',
];

// --- Helper Functions ---
function executeCommand(string $command, ?string $cwd = null): array {
    // ... (این تابع دیگر برای دستورات git استفاده نمی‌شود، اما ممکن است برای کارهای دیگر مفید باشد)
    // ... (اگر فقط برای echo استفاده می‌شود، می‌توان آن را ساده‌تر کرد یا حذف کرد)
    echo "Executing (Placeholder): {$command}\n"; // تغییر یافته
    return [];
}

function createDirectory(string $path): void {
    if (!is_dir($path)) {
        echo "Creating directory: {$path}\n";
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Failed to create directory "%s"', $path));
        }
    }
}

// --- Script Logic ---

echo "=====================================\n";
echo " Starting Full Update Package Builder (No Git)\n";
echo "=====================================\n";

// 1. Parse Arguments
if ($argc < 2) {
    echo "Usage: php " . basename(__FILE__) . " <new_version>\n";
    echo "Example: php " . basename(__FILE__) . " 1.1.0\n";
    exit(1);
}

$newVersion = trim($argv[1]);
if (!preg_match('/^\d+(\.\d+)+([-.].+)?$/', $newVersion)) {
    echo "Error: Invalid new version format provided: {$newVersion}\n";
    exit(1);
}

// $previousVersionTag = $argv[2] ?? null; // دیگر نیازی به این نیست

try {
    createDirectory($outputDir);

    // 2. Get All Project Files (excluding specified ones)
    echo "\nCollecting all project files...\n";
    $allFilesToInclude = [];
    $projectFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST // SELF_FIRST برای بررسی پوشه‌ها قبل از محتویاتشان
    );

    foreach ($projectFiles as $item) {
        $filePathRelative = substr($item->getPathname(), strlen($projectRoot) + 1);
        $filePathRelative = str_replace('\\', '/', $filePathRelative); // یکسان‌سازی جداکننده مسیر

        // Check against exclusion list
        $exclude = false;
        foreach ($filesToExclude as $pattern) {
            // اگر الگو با / ختم شود، یعنی فقط پوشه با آن نام
            if (str_ends_with($pattern, '/') && $item->isDir()) {
                if (fnmatch(rtrim($pattern, '/'), $filePathRelative, FNM_PATHNAME | FNM_CASEFOLD) ||
                    fnmatch(rtrim($pattern, '/') . '/*', $filePathRelative, FNM_PATHNAME | FNM_CASEFOLD)) {
                     $exclude = true;
                     break;
                }
            } elseif (fnmatch($pattern, $filePathRelative, FNM_PATHNAME | FNM_CASEFOLD)) {
                 // اگر الگو با / ختم نشود، می‌تواند فایل یا پوشه باشد
                 // اگر آیتم یک پوشه است و الگو دقیقاً نام پوشه است (بدون /*)
                 if ($item->isDir() && $pattern === $filePathRelative) {
                    // برای اینکه محتویات پوشه exclude شده هم کپی نشود
                    // باید یک /* هم به الگو اضافه کنیم اگر الگو فقط نام پوشه است
                    if(fnmatch($pattern . '/*', $filePathRelative . '/dummy_child', FNM_PATHNAME | FNM_CASEFOLD)) {
                        $exclude = true;
                        break;
                    }
                 } else if (!$item->isDir()) { // اگر فایل است
                    $exclude = true;
                    break;
                 }
            }
        }
        
        // یک بررسی دقیق‌تر برای پوشه‌ها
        if (!$exclude && $item->isDir()) {
            foreach ($filesToExclude as $pattern) {
                 // اگر الگو با / ختم می‌شود، به معنای یک پوشه است
                if (str_ends_with($pattern, '/')) {
                    $dirPattern = rtrim($pattern, '/');
                     // اگر مسیر فعلی با الگوی پوشه شروع شود
                    if (str_starts_with($filePathRelative . '/', $dirPattern . '/')) {
                        $exclude = true;
                        break;
                    }
                }
            }
        }


        if (!$exclude && !$item->isDir()) { // فقط فایل‌ها را برای کپی مستقیم اضافه کن
            $allFilesToInclude[] = $filePathRelative;
        } elseif ($exclude && $item->isDir()) {
            // اگر یک پوشه exclude شده، از ادامه پیمایش آن صرف نظر کن (اگر Iterator اجازه دهد)
            // RecursiveDirectoryIterator::SKIP_DOTS این کار را نمی‌کند.
            // باید به صورت دستی از اضافه کردن محتویات آن صرف نظر کنیم.
            // این بخش نیاز به اصلاح دارد تا به درستی پوشه‌های exclude شده را رد کند.
            // برای سادگی فعلی، exclude کردن بر اساس فایل انجام می‌شود و پوشه‌ها بر اساس الگوی اولیه.
        }
    }
    
    // پاکسازی برای حذف مسیرهایی که ممکن است به دلیل exclude پوشه والد هنوز در لیست باشند
    // این بخش پیچیده است و نیاز به بازنگری دارد اگر بخواهیم exclude پوشه‌ها به درستی کار کند
    // با RecursiveIteratorIterator. فعلا بر اساس exclude فایل‌ها کار می‌کنیم.
    // یک راه ساده‌تر این است که پس از جمع‌آوری همه چیز، دوباره لیست را فیلتر کنیم.
    
    $finalFilesToInclude = [];
    foreach ($allFilesToInclude as $fileRel) {
        $isExcluded = false;
        foreach ($filesToExclude as $pattern) {
            // چک کردن دقیق‌تر الگوها برای فایل‌ها
            if (fnmatch($pattern, $fileRel, FNM_PATHNAME | FNM_CASEFOLD)) {
                $isExcluded = true;
                break;
            }
            // چک کردن اگر فایل داخل یک پوشه exclude شده باشد
            if (str_ends_with($pattern, '/')) {
                 if (str_starts_with($fileRel, rtrim($pattern, '/').'/')) {
                    $isExcluded = true;
                    break;
                 }
            }
        }
        if (!$isExcluded) {
            $finalFilesToInclude[] = $fileRel;
        }
    }
    $allFilesToInclude = array_unique($finalFilesToInclude);


    if (empty($allFilesToInclude)) {
        echo "\nError: No files identified for inclusion in the update package (after exclusions). Aborting.\n";
        print_r($filesToExclude); // برای دیباگ
        exit(1);
    }
    echo "Found " . count($allFilesToInclude) . " application file(s) to include.\n";
    // echo "Sample files: \n" . implode("\n", array_slice($allFilesToInclude, 0, 10)) . "\n"; // برای دیباگ

    // 3. Create Temp Directory
    echo "\nCreating temporary build directory...\n";
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'update_build_' . uniqid();
    createDirectory($tempDir);
    echo "Temporary directory: {$tempDir}\n";

    // 4. Copy Files to Temp Directory
    echo "Copying files to temporary directory...\n";
    foreach ($allFilesToInclude as $fileRelative) {
        $sourcePath = $projectRoot . '/' . $fileRelative;
        $destinationPath = $tempDir . '/' . $fileRelative;

        if (!file_exists($sourcePath)) {
            echo "Warning: Source file does not exist, skipping: {$sourcePath}\n";
            continue;
        }

        $destinationDir = dirname($destinationPath);
        createDirectory($destinationDir);

        if (!copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("Failed to copy file: {$sourcePath} to {$destinationPath}");
        }
    }
    echo count($allFilesToInclude) . " file(s) copied.\n";

    // 5. Create ZIP Archive
    echo "\nCreating ZIP archive...\n";
    $zipFileName = sprintf($zipFileNameFormat, $newVersion);
    $zipFilePath = $outputDir . '/' . $zipFileName;

    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new RuntimeException("Cannot create ZIP file: {$zipFilePath}");
    }

    $filesInTemp = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($filesInTemp as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($tempDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();
    echo "ZIP archive created successfully: {$zipFilePath}\n";

    // 6. Calculate Checksum
    echo "Calculating Checksum (SHA256)...\n";
    $checksum = hash_file('sha256', $zipFilePath);
    if ($checksum === false) {
         throw new RuntimeException("Failed to calculate checksum for ZIP file: {$zipFilePath}");
    }
     echo "Checksum: {$checksum}\n";

    // 7. Generate latest.json
    echo "Generating latest.json file...\n";
    $latestJsonPath = $outputDir . '/' . $latestJsonFileName;

    $latestDataContent = [
        'version' => $newVersion,
        'zip_url' => rtrim($publicBaseUrlForDownloads, '/') . '/' . $zipFileName,
        'checksum' => $checksum,
        'release_date' => date('c'), // فرمت ISO 8601 (YYYY-MM-DDTHH:MM:SS+HH:MM)
        'changelog' => "به‌روزرسانی کامل برای نسخه {$newVersion}.", // می‌توانید این را دقیق‌تر کنید
        // در صورت نیاز می‌توانید کلیدهای دیگری مثل notes_url هم اینجا اضافه کنید
    ];

    $latestJsonData = [
        'latest' => $latestDataContent,
        // اگر می‌خواهید بخش current را هم داشته باشید (اختیاری)
        /*
        'current' => [
            'version' => $newVersion, // یا نسخه قبلی، بسته به منطق شما
            'changelog' => "توضیحات نسخه فعلی پس از آپدیت."
        ]
        */
    ];

    if (file_put_contents($latestJsonPath, json_encode($latestJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        throw new RuntimeException("Failed to write latest.json file: {$latestJsonPath}");
    }
     echo "latest.json file created successfully: {$latestJsonPath}\n";

    // 8. Clean up Temp Directory
    echo "Cleaning up temporary directory...\n";
    function recursiveRmdir(string $dir): void {
         if (!is_dir($dir)) return;
         $iterator = new RecursiveIteratorIterator(
             new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
             RecursiveIteratorIterator::CHILD_FIRST
         );
         foreach ($iterator as $file) {
             if ($file->isDir()) rmdir($file->getRealPath());
             else unlink($file->getRealPath());
         }
         rmdir($dir);
    }
     recursiveRmdir($tempDir);
    echo "Temporary directory cleaned up.\n";

    echo "\n=====================================\n";
    echo " Build Script Finished Successfully! \n";

} catch (Throwable $e) {
    echo "\n================= ERROR =================\n";
    echo "Build failed: " . $e->getMessage() . "\n";
    echo "In file: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    // echo $e->getTraceAsString() . "\n"; // برای خلاصه بودن می‌توان این را کامنت کرد
    echo "=========================================\n";
    exit(1);
}

exit(0);
