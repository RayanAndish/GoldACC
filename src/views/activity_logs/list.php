<?php
/**
 * Template: src/views/activity_logs/list.php
 * Displays the list of system activity logs with search and pagination.
 */

use App\Utils\Helper;

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'گزارش فعالیت‌ها';
$logs = $viewData['logs'] ?? [];
$errorMessage = $viewData['error_msg'] ?? null;
$searchTerm = $viewData['search_term'] ?? '';
$filterType = $viewData['filter_type'] ?? '';
$pagination = $viewData['pagination'] ?? null;
$baseUrl = $viewData['baseUrl'] ?? '';

// Build base URL for pagination and form actions
$pageBaseUrl = $baseUrl . '/app/activity-logs';
$queryParams = array_filter(['search' => $searchTerm, 'type' => $filterType]);
$queryString = http_build_query($queryParams);
$pageUrlWithFilters = $pageBaseUrl . ($queryString ? '?' . $queryString : '');
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <div>
        <button onclick="printActivityLogTable()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> چاپ</button>
        <button onclick="exportActivityLogToExcel()" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel me-1"></i> خروجی اکسل</button>
    </div>
</div>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?php echo Helper::escapeHtml($errorMessage); ?></div>
<?php endif; ?>

<!-- Filter Form -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form action="<?php echo $pageBaseUrl; ?>" method="GET" class="row g-3 align-items-center">
            <div class="col-md-5">
                <input type="text" class="form-control form-control-sm" name="search" placeholder="جستجو در جزئیات، نوع، IP، کاربر..." value="<?php echo Helper::escapeHtml($searchTerm); ?>">
            </div>
            <div class="col-md-4">
                <select name="type" class="form-select form-select-sm">
                    <option value="">همه انواع عملیات</option>
                    <option value="SUCCESS" <?php echo ($filterType === 'SUCCESS') ? 'selected' : ''; ?>>موفقیت (SUCCESS)</option>
                    <option value="ERROR" <?php echo ($filterType === 'ERROR') ? 'selected' : ''; ?>>خطا (ERROR)</option>
                    <option value="INFO" <?php echo ($filterType === 'INFO') ? 'selected' : ''; ?>>اطلاع (INFO)</option>
                    <option value="WARNING" <?php echo ($filterType === 'WARNING') ? 'selected' : ''; ?>>هشدار (WARNING)</option>
                    <option value="SECURITY" <?php echo ($filterType === 'SECURITY') ? 'selected' : ''; ?>>امنیتی (SECURITY)</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100">فیلتر</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (!empty($logs)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0" id="activityLogsTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>کاربر</th>
                            <th>نوع عملیات</th>
                            <th>سطح</th>
                            <th>IP</th>
                            <th>جزئیات</th>
                            <th>زمان</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $index => $log): ?>
                            <?php
                                $rowNumber = (($pagination['currentPage'] - 1) * $pagination['limit']) + $index + 1;
                                $badgeClass = match(strtoupper($log['level_name'] ?? 'INFO')) {
                                    'CRITICAL', 'ERROR' => 'bg-danger',
                                    'WARNING' => 'bg-warning text-dark',
                                    'SUCCESS' => 'bg-success',
                                    'INFO', 'DEBUG' => 'bg-info text-dark',
                                    default => 'bg-secondary'
                                };
                            ?>
                            <tr>
                                <td class="number-fa"><?php echo $rowNumber; ?></td>
                                <td><?php echo Helper::escapeHtml($log['username']); ?></td>
                                <td><?php echo Helper::escapeHtml($log['action_type']); ?></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo Helper::escapeHtml($log['level_name']); ?></span></td>
                                <td class="number-fa"><?php echo Helper::escapeHtml($log['ip_address']); ?></td>
                                <td class="text-start" style="max-width: 400px;"><?php echo $log['action_details_display']; // This is pre-formatted with <pre> and escaped ?></td>
                                <td class="number-fa text-nowrap"><?php echo Helper::escapeHtml($log['created_at_persian']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pagination && $pagination['totalPages'] > 1): ?>
                <div class="card-footer text-center py-2">
                    <?php
                    $baseUrlForPagination = $pageUrlWithFilters;
                    include __DIR__ . '/../partials/pagination.php';
                    ?>
                </div>
            <?php endif; ?>

        <?php elseif (empty($errorMessage)): ?>
            <p class="text-center text-muted p-4 mb-0">
                 <?php echo empty($searchTerm) && empty($filterType) ? 'هیچ گزارشی ثبت نشده است.' : 'موردی با این مشخصات یافت نشد.'; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts for this page -->
<script src="<?php echo $baseUrl; ?>/js/xlsx.full.min.js"></script>

<script>
function printActivityLogTable() {
    const tableHtml = document.getElementById('activityLogsTable').outerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(
        '<html lang="fa" dir="rtl">' +
        '<head>' +
        '<title>چاپ گزارش فعالیت‌ها</title>' +
        // FIX: Use local bootstrap path
        '<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/bootstrap.rtl.min.css">' +
        // FIX: Added necessary print styles
        '<style>' +
        'body { font-family: "Vazirmatn", sans-serif; padding: 15px; background-color: #fff !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;}' +
        'table { width: 100%; border-collapse: collapse; font-size: 9pt; }' +
        'th, td { border: 1px solid #ccc; padding: 5px; text-align: right; vertical-align: top; }' +
        'thead th { background-color: #f2f2f2 !important; font-weight: bold; }' +
        'tbody tr:nth-child(even) { background-color: #fafafa !important; }' +
        'h3 { text-align: center; margin-bottom: 20px; }' +
        'pre { white-space: pre-wrap; word-wrap: break-word; font-family: inherit; margin: 0; font-size: 0.9em;}' +
        '.number-fa { font-family: "Vazirmatn", monospace; text-align: center; direction: ltr; }' +
        '.badge { display: inline-block; padding: .35em .65em; font-size: .75em; font-weight: 700; line-height: 1; color: #fff; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; }' +
        '.bg-danger { background-color: #dc3545 !important; } ' +
        '.bg-warning { background-color: #ffc107 !important; color: #000 !important; } ' +
        '.bg-success { background-color: #198754 !important; } ' +
        '.bg-info { background-color: #0dcaf0 !important; color: #000 !important; } ' +
        '.bg-secondary { background-color: #6c757d !important; } ' +
        '@page { size: A4 landscape; margin: 1cm; }' +
        '</style>' +
        '</head>' +
        '<body>' +
        '<h3>گزارش فعالیت‌های سیستم</h3>' +
        tableHtml +
        '<script>setTimeout(function(){window.print();window.close();}, 500);<\/script>' +
        '</body></html>'
    );
    printWindow.document.close();
}

function exportActivityLogToExcel() {
    try {
        const table = document.getElementById('activityLogsTable');
        const wb = XLSX.utils.table_to_book(table, {sheet: "Activity Logs"});
        const now = new Date();
        const fileName = "Activity_Logs_" +
            now.getFullYear().toString() +
            ("0" + (now.getMonth() + 1)).slice(-2) +
            ("0" + now.getDate()).slice(-2) + "_" +
            ("0" + now.getHours()).slice(-2) +
            ("0" + now.getMinutes()).slice(-2) +
            ".xlsx";
        XLSX.writeFile(wb, fileName);
    } catch (e) {
        console.error("Failed to export to Excel", e);
        alert("خطا در ایجاد فایل اکسل.");
    }
}
</script>