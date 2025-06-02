<?php
// Template: لیست نسخه های پشتیبان سامانه و مدیریت آن
?>
<h1 class="mb-4">مدیریت نسخه های پشتیبان سامانه</h1>
<div class="mb-3">
    <form method="post" action="/app/system/backup/run" style="display:inline-block">
        <button type="submit" class="btn btn-success">ایجاد نسخه پشتیبان جدید</button>
    </form>
</div>
<table class="table table-bordered table-striped text-center">
    <thead class="table-light">
        <tr>
            <th>نام فایل</th>
            <th>تاریخ</th>
            <th>حجم</th>
            <th>دانلود</th>
            <!--<th>بازگردانی</th>-->
        </tr>
    </thead>
    <tbody>
        <?php foreach($backups as $b): ?>
        <tr>
            <td class="number-fa"><?php echo htmlspecialchars($b['name']); ?></td>
            <td class="number-fa"><?php echo date('Y-m-d H:i', $b['modified'] ?? time()); ?></td>
            <td class="number-fa"><?php echo round(($b['size'] ?? 0)/1024/1024, 2); ?> MB</td>
            <td><a href="/app/system/download-backup/<?php echo urlencode($b['name']); ?>" class="btn btn-sm btn-primary">دانلود</a></td>
            <!--<td><form method="post" action="/app/system/backup/restore"><input type="hidden" name="filename" value="<?php echo htmlspecialchars($b['name']); ?>"><button type="submit" class="btn btn-sm btn-warning">بازگردانی</button></form></td>-->
        </tr>
        <?php endforeach; ?>
        <?php if (empty($backups)): ?>
        <tr><td colspan="4" class="text-muted">هیچ نسخه پشتیبانی وجود ندارد.</td></tr>
        <?php endif; ?>
    </tbody>
</table> 