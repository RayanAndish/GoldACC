<?php
// Partial pagination component
// $pagination: array (totalRecords, totalPages, currentPage, limit)
// $baseUrl: string (base url for links)
if (!empty($pagination) && ($pagination['totalPages'] ?? 1) > 1):
?>
<nav>
  <ul class="pagination justify-content-center my-2">
    <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
      <li class="page-item<?php echo $i == $pagination['currentPage'] ? ' active' : ''; ?>">
        <a class="page-link" href="<?php echo $baseUrl; ?>?p=<?php echo $i; ?>">
          <?php echo $i; ?>
        </a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?> 