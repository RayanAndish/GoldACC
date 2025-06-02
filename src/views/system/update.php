<?php
/**
 * Template: src/views/system/update.php
 * صفحه به‌روزرسانی سامانه و تاریخچه به‌روزرسانی
 */
$updateHistory = $viewData['update_history'] ?? [];
$pagination = $viewData['update_pagination'] ?? [];
$currentVersion = $viewData['current_version'] ?? '-';
$baseUrl = $viewData['baseUrl'] ?? '';
$success_msg = $viewData['success_msg'] ?? null;
$error_msg = $viewData['error_msg'] ?? null;
?>

<h1 class="mb-4">به‌روزرسانی سامانه</h1>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
<?php endif; ?>

<div class="card mb-4 shadow-sm">
    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
        نسخه فعلی: <span class="fw-bold number-fa text-primary"><?php echo htmlspecialchars($currentVersion); ?></span>
    </div>
    <div class="card-body">
        <form method="post" action="/app/system/update/check">
            <button type="submit" class="btn btn-info">بررسی به‌روزرسانی جدید</button>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
        تاریخچه به‌روزرسانی
        <?php if ($pagination && $pagination['totalRecords'] > 0): ?>
            <small class="text-muted">
                نمایش <?php echo (($pagination['currentPage']-1) * $pagination['limit']) + 1; ?>
                - <?php echo min($pagination['totalRecords'], $pagination['currentPage'] * $pagination['limit']); ?>
                از <?php echo $pagination['totalRecords']; ?>
            </small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>شماره نسخه</th>
                        <th>تاریخ به‌روزرسانی</th>
                        <th>نتیجه</th>
                        <th>گزارش</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($updateHistory)): ?>
                        <?php foreach ($updateHistory as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['version']); ?></td>
                                <td><?php echo jdate('Y/m/d H:i', strtotime($row['update_time'])); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'success'): ?>
                                        <span class="badge bg-success">موفق</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">ناموفق</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo $baseUrl; ?>/app/system/update/report/<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-info">مشاهده گزارش</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted">هیچ سابقه‌ای ثبت نشده است.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        $baseUrlForPagination = $baseUrl . '/app/system/update';
        include __DIR__ . '/../partials/pagination.php';
        ?>
    </div>
</div> 