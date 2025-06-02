<?php
/**
 * Template: src/views/activity_logs/list.php (یا reports/list.php)
 * Displays the list of system activity logs with search and pagination.
 * Receives data via $viewData array from ActivityLogsController.
 */

use App\Utils\Helper; // Use the Helper class

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'گزارش فعالیت‌ها';
$logs = $viewData['logs'] ?? [];
$successMessage = $viewData['success_msg'] ?? null; // Get potential flash success
$errorMessage = $viewData['error_msg'] ?? null;   // Get potential flash error
$searchTerm = $viewData['search_term'] ?? '';     // Current search term
$pagination = $viewData['pagination'] ?? null;     // Pagination data array
// مقداردهی پیش‌فرض برای جلوگیری از Undefined array key
if (!is_array($pagination)) $pagination = [];
$pagination['totalRecords'] = $pagination['totalRecords'] ?? 0;
$pagination['totalPages'] = $pagination['totalPages'] ?? 1;
$pagination['currentPage'] = $pagination['currentPage'] ?? 1;
$pagination['limit'] = $pagination['limit'] ?? 15;
$baseUrl = $viewData['baseUrl'] ?? '';             // Base URL

// Base URL for this page for filter form and pagination links
$pageBaseUrl = $baseUrl . '/app/activity-logs'; // Use the correct route
// Build query string from current filters (only search term in this case)
$queryParams = array_filter(['search' => $searchTerm, 'type' => $viewData['filter_type'] ?? '']);
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';

$baseUrlForPagination = $pageBaseUrl . $queryString;

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
     <?php // Optional: Add buttons for Export/Print (handled by JS below) ?>
     <div class="d-flex gap-2">
         <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printActivityLogTable();">
             <i class="fas fa-print me-1"></i> چاپ
         </button>
         <button type="button" class="btn btn-sm btn-outline-success" onclick="exportActivityLogToExcel();">
             <i class="fas fa-file-excel me-1"></i> خروجی اکسل
         </button>
     </div>
</div>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo Helper::escapeHtml($successMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo Helper::escapeHtml($errorMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php // --- Search Form --- ?>
<div class="card shadow-sm mb-3">
    <div class="card-body p-2">
        <form method="GET" action="<?php echo $pageBaseUrl; ?>" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <label for="search" class="visually-hidden">جستجو</label>
                 <input type="text" class="form-control form-control-sm" id="search" name="search" value="<?php echo Helper::escapeHtml($searchTerm); ?>" placeholder="جستجو در کاربر، عملیات، جزئیات، IP...">
            </div>
            <div class="col-md-3 col-lg-2">
                <label for="logTypeFilter" class="visually-hidden">نوع عملیات</label>
                <select class="form-select form-select-sm" id="logTypeFilter" name="type">
                    <option value="">همه عملیات</option>
                    <!-- مقادیر زیر باید با مقادیر واقعی ستون action_type در دیتابیس مطابقت داشته باشند -->
                    <option value="SUCCESS" <?php echo (($viewData['filter_type'] ?? '') === 'SUCCESS') ? 'selected' : ''; ?>>موفقیت (SUCCESS)</option>
                    <option value="ERROR" <?php echo (($viewData['filter_type'] ?? '') === 'ERROR') ? 'selected' : ''; ?>>خطا (ERROR)</option>
                    <option value="WARNING" <?php echo (($viewData['filter_type'] ?? '') === 'WARNING') ? 'selected' : ''; ?>>هشدار (WARNING)</option>
                    <option value="INFO" <?php echo (($viewData['filter_type'] ?? '') === 'INFO') ? 'selected' : ''; ?>>اطلاعاتی (INFO)</option>
                    <option value="DEBUG" <?php echo (($viewData['filter_type'] ?? '') === 'DEBUG') ? 'selected' : ''; ?>>اشکال‌زدایی (DEBUG)</option>
                    <option value="LOGIN" <?php echo (($viewData['filter_type'] ?? '') === 'LOGIN') ? 'selected' : ''; ?>>ورود (LOGIN)</option>
                    <option value="LOGOUT" <?php echo (($viewData['filter_type'] ?? '') === 'LOGOUT') ? 'selected' : ''; ?>>خروج (LOGOUT)</option>
                    <option value="GENERAL" <?php echo (($viewData['filter_type'] ?? '') === 'GENERAL') ? 'selected' : ''; ?>>عمومی (GENERAL)</option>
                    <option value="CRITICAL" <?php echo (($viewData['filter_type'] ?? '') === 'CRITICAL') ? 'selected' : ''; ?>>بحرانی (CRITICAL)</option>
                    <option value="SETTINGS_UPDATE" <?php echo (($viewData['filter_type'] ?? '') === 'SETTINGS_UPDATE') ? 'selected' : ''; ?>>ذخیره تنظیمات</option>
                    <option value="BACKUP_DELETE" <?php echo (($viewData['filter_type'] ?? '') === 'BACKUP_DELETE') ? 'selected' : ''; ?>>حذف پشتیبان</option>
                    <!-- سایر action_type های مورد استفاده در سیستم را اینجا اضافه کنید -->
                </select>
            </div>
            <div class="col-auto">
                 <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
             </div>
            <?php if (!empty($searchTerm)): ?>
            <div class="col-auto">
                <a href="<?php echo $pageBaseUrl; ?>" class="btn btn-sm btn-outline-secondary" title="پاک کردن فیلترها"><i class="fas fa-times"></i></a>
            </div>
            <?php endif; ?>
         </form>
    </div>
</div>


<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">گزارش فعالیت‌ها</h5>
         <?php if ($pagination && $pagination['totalRecords'] > 0): ?>
            <small class="text-muted">
                نمایش <?php echo (($pagination['currentPage']-1) * $pagination['limit']) + 1; ?>
                - <?php echo min($pagination['totalRecords'], $pagination['currentPage'] * $pagination['limit']); ?>
                از <?php echo $pagination['totalRecords']; ?>
            </small>
        <?php else: ?>
             <small class="text-muted">مجموع: 0</small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm align-middle mb-0" id="activityLogsTable">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th>کاربر</th>
                        <th>عملیات</th>
                        <th style="min-width: 250px;">جزئیات</th>
                        <th>IP</th>
                        <th>Ray ID</th>
                        <th class="text-nowrap">زمان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)):
                          $startRow = (($pagination['currentPage'] ?? 1) - 1) * ($pagination['limit'] ?? 15) + 1;
                          foreach ($logs as $index => $log):
                              $levelName = strtoupper($log['level_name'] ?? 'INFO');
                              $badgeClass = match ($levelName) {
                                  'CRITICAL', 'ERROR', 'ALERT', 'EMERGENCY' => 'bg-danger',
                                  'WARNING' => 'bg-warning text-dark',
                                  'NOTICE' => 'bg-info',
                                  'DEBUG' => 'bg-secondary',
                                  default => 'bg-primary',
                              };
                              // کلاس ردیف بر اساس نوع لاگ
                              $rowClass = match ($levelName) {
                                  'CRITICAL', 'ERROR', 'ALERT', 'EMERGENCY' => 'log-error',
                                  'WARNING' => 'log-warning',
                                  'NOTICE', 'INFO' => 'log-info',
                                  'DEBUG' => 'log-debug',
                                  'TRACE' => 'log-trace',
                                  default => '',
                              };
                    ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td class="text-center small"><?php echo $startRow + $index; ?></td>
                                <td class="small text-nowrap"><?php echo $log['username']; // Escaped in controller ?></td>
                                <td class="small">
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo $log['action_type']; // Escaped in controller ?>
                                    </span>
                                    <small class="text-muted ms-1">(<?php echo $log['level_name'] ?? 'INFO';?>)</small>
                                </td>
                                <td class="small">
                                    <?php
                                    // نمایش جزئیات لاگ به صورت لیست کلید-مقدار اگر JSON معتبر بود
                                    $details = $log['action_details'] ?? $log['action_details_display'] ?? null;
                                    $decoded = json_decode($details, true);
                                    if (is_array($decoded)) {
                                        echo '<ul class="mb-0 ps-3 small">';
                                        foreach ($decoded as $k => $v) {
                                            if (is_array($v) || is_object($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                                            echo '<li><strong>' . Helper::escapeHtml($k) . ':</strong> ' . Helper::escapeHtml($v) . '</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo Helper::escapeHtml($details);
                                    }
                                    ?>
                                </td>
                                <td class="small number-fa"><?php echo $log['ip_address']; // Escaped ?></td>
                                <td class="small" title="<?php echo $log['ray_id']; // Escaped ?>">
                                     <?php echo $log['ray_id'] ? substr($log['ray_id'], 0, 10) . '...' : '-'; ?>
                                 </td>
                                <td class="small text-nowrap number-fa">
                                    <?php echo $log['created_at_persian'] ?? '-'; // Formatted in controller ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-4">
                                <?php echo empty($searchTerm) ? 'هیچ گزارشی ثبت نشده است.' : 'موردی با این مشخصات یافت نشد.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div> <?php // end card-body ?>

    <?php // --- Pagination Links --- ?>
    <?php if ($pagination && $pagination['totalPages'] > 1): ?>
        <div class="card-footer text-center py-2 bg-light border-top">
            <?php include __DIR__ . '/../partials/pagination.php'; ?>
        </div>
    <?php endif; ?>

</div> <?php // end card ?>

<!-- اسکریپت‌های جاوااسکریپت باید خارج از بلاک PHP و در انتهای فایل باشند -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
function printActivityLogTable() {
    const tableHtml = document.getElementById('activityLogsTable').outerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(
        '<html lang="fa" dir="rtl">' +
        '<head>' +
        '<title>چاپ گزارش فعالیت‌ها</title>' +
        '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">' +
        '<style> /* ... استایل‌ها ... */ </style>' +
        '</head>' +
        '<body>' +
        '<h3 class="text-center my-3">گزارش فعالیت‌های سیستم</h3>' +
        tableHtml +
        '<script>setTimeout(function(){window.print();window.close();},500);<\/script>' +
        '</body></html>'
    );
    printWindow.document.close();
}

function exportActivityLogToExcel() {
    try {
        const table = document.getElementById('activityLogsTable');
        const wb = XLSX.utils.table_to_book(table, {sheet: "Activity Logs"});
        // تاریخ و زمان فعلی برای نام فایل
        const now = new Date();
        const fileName = "Activity_Logs_" +
            now.getFullYear().toString() +
            ("0" + (now.getMonth()+1)).slice(-2) +
            ("0" + now.getDate()).slice(-2) + "_" +
            ("0" + now.getHours()).slice(-2) +
            ("0" + now.getMinutes()).slice(-2) +
            ("0" + now.getSeconds()).slice(-2) +
            ".xlsx";
        XLSX.writeFile(wb, fileName);
    } catch (error) {
        console.error("Excel export failed:", error);
        alert("خطا در ایجاد فایل اکسل. لطفاً کتابخانه SheetJS را بررسی کنید.");
    }
}
</script>