<?php
/**
 * Template: src/views/users/list.php
 * Displays the list of system users. (Admin only)
 * Receives data via $viewData array from UserController.
 */

use App\Utils\Helper; // Use the Helper class
use Morilog\Jalali\Jalalian; // Add Jalalian namespace

// --- Extract data from $viewData ---
$pageTitle = $viewData['page_title'] ?? 'مدیریت کاربران';
$users = $viewData['users'] ?? [];
$successMessage = $viewData['success_msg'] ?? null; // Success message from controller/session
$errorMessage = $viewData['error_msg'] ?? null;   // Error message from controller/session
$baseUrl = $viewData['baseUrl'] ?? '';

?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="m-0"><?php echo Helper::escapeHtml($pageTitle); ?></h1>
    <a href="<?php echo $baseUrl; ?>/app/users/add" class="btn btn-success btn-sm"><i class="fas fa-user-plus me-1"></i>افزودن کاربر جدید</a>
</div>

<?php // --- Display Messages --- ?>
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo Helper::escapeHtml($successMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo Helper::escapeHtml($errorMessage); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">کاربران سیستم</h5>
        <small class="text-muted">مجموع: <?php echo count($users); ?> کاربر</small>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">ID</th>
                            <th>نام کاربری</th>
                            <th>نام نمایشی</th>
                            <th>نقش</th>
                            <th class="text-center">وضعیت</th>
                            <th>تاریخ ایجاد</th>
                            <th class="text-center" style="width: 120px;">عملیات</th> <?php // Width for buttons ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user):
                            $userId = (int)$user['id'];
                            $isActive = (bool)($user['is_active'] ?? false);
                            $isAdmin = ($user['role_name'] ?? '') === 'admin'; // Check if role is admin based on name
                            $isSuperAdmin = $userId === 1; // Assuming ID 1 is super admin
                            $isCurrentUser = $userId === ($_SESSION['user_id'] ?? null);
                        ?>
                            <tr>
                                <td class="text-center small fw-bold"><?php echo $userId; ?></td>
                                <td class="fw-bold"><?php echo Helper::escapeHtml($user['username']); ?></td>
                                <td><?php echo Helper::escapeHtml($user['name'] ?: '-'); ?></td>
                                <td><?php echo Helper::escapeHtml($user['role_name'] ?? 'نامشخص'); ?></td>
                                <td class="text-center">
                                     <?php if ($isActive): ?>
                                         <span class="badge bg-success">فعال</span>
                                     <?php else: ?>
                                          <span class="badge bg-secondary">غیرفعال</span>
                                     <?php endif; ?>
                                 </td>
                                <td class="small text-nowrap"><?php echo $user['created_at'] ? Jalalian::fromFormat('Y-m-d H:i:s', $user['created_at'])->format('Y/m/d H:i') : '-'; ?></td>
                                <td class="text-center text-nowrap">
                                     <?php // Edit Button ?>
                                     <a href="<?php echo $baseUrl; ?>/app/users/edit/<?php echo $userId; ?>"
                                        class="btn btn-sm btn-outline-primary btn-action py-0 px-1 me-1" data-bs-toggle="tooltip" title="ویرایش">
                                         <i class="fas fa-edit fa-xs"></i>
                                     </a>

                                    <?php // Toggle Active Button (POST Form) - Cannot toggle super admin or self ?>
                                    <?php if (!$isSuperAdmin && !$isCurrentUser): ?>
                                        <form action="<?php echo $baseUrl; ?>/app/users/toggle-active/<?php echo $userId; ?>" method="POST" class="d-inline"
                                              onsubmit="return confirm('آیا از <?php echo $isActive ? 'غیرفعال' : 'فعال'; ?> کردن کاربر ' + <?php echo json_encode($user['username']);?> + ' مطمئن هستید؟');">
                                            <?php // TODO: CSRF Token ?>
                                            <button type="submit" class="btn btn-sm <?php echo $isActive ? 'btn-outline-warning' : 'btn-outline-success'; ?> btn-action py-0 px-1 me-1"
                                                    data-bs-toggle="tooltip" title="<?php echo $isActive ? 'غیرفعال کردن' : 'فعال کردن'; ?>">
                                                 <i class="fas <?php echo $isActive ? 'fa-user-slash' : 'fa-user-check'; ?> fa-xs"></i>
                                            </button>
                                        </form>
                                    <?php else: // Show disabled icon ?>
                                         <span class="btn btn-sm btn-outline-secondary py-0 px-1 me-1 disabled" data-bs-toggle="tooltip" title="تغییر وضعیت مجاز نیست">
                                              <i class="fas <?php echo $isActive ? 'fa-user-slash' : 'fa-user-check'; ?> fa-xs"></i>
                                         </span>
                                    <?php endif; ?>

                                    <?php // Delete Button (POST Form) - Cannot delete super admin or self ?>
                                     <?php if (!$isSuperAdmin && !$isCurrentUser): ?>
                                         <form action="<?php echo $baseUrl; ?>/app/users/delete/<?php echo $userId; ?>" method="POST" class="d-inline"
                                               onsubmit="return confirm('اخطار! آیا از حذف کامل کاربر ' + <?php echo json_encode($user['username']);?> + ' مطمئن هستید؟ این عمل قابل بازگشت نیست!');">
                                             <?php // TODO: CSRF Token ?>
                                             <button type="submit" class="btn btn-sm btn-outline-danger btn-action py-0 px-1" data-bs-toggle="tooltip" title="حذف کاربر">
                                                 <i class="fas fa-trash-can fa-xs"></i>
                                             </button>
                                         </form>
                                     <?php else: // Show disabled icon ?>
                                          <span class="btn btn-sm btn-outline-secondary py-0 px-1 disabled" data-bs-toggle="tooltip" title="حذف کاربر مجاز نیست">
                                                <i class="fas fa-trash-can fa-xs"></i>
                                          </span>
                                     <?php endif; ?>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                    </tbody>
                 </table>
            </div>
         <?php elseif (!$errorMessage): ?>
            <p class="text-center text-muted p-3 mb-0">کاربری در سیستم یافت نشد.</p>
        <?php endif; ?>
    </div> <?php // end card body ?>
</div> <?php // end card ?>

<?php // Tooltip Script (if not global in footer) ?>
<script> /* Initialize Bootstrap Tooltips */ </script>