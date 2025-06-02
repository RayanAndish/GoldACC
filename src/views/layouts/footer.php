<?php
/**
 * Template: src/views/layouts/footer.php
 * Main application footer and closing HTML tags.
 */

use App\Utils\Helper;

// --- Extract common data ---
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? '';

$userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if ($userIP === '::1') $userIP = '127.0.0.1';
$rayId = (session_status() == PHP_SESSION_ACTIVE && isset($_SESSION['ray_id'])) ? $_SESSION['ray_id'] : null;

?>

    </main>

    <footer class="footer mt-auto py-3 bg-dark text-white-50 border-top border-secondary-subtle shadow-sm">
        <div class="container text-center">
            <small>
                © <?php echo date('Y'); ?> <?php echo Helper::escapeHtml($appName); ?>. تمامی حقوق محفوظ است.
                <?php if ($rayId): ?>
                    <span class="mx-2 text-white-50">|</span>
                    <span class="user-select-all" title="Ray ID: <?php echo Helper::escapeHtml($rayId); ?>">
                        <i class="fas fa-fingerprint fa-fw"></i> <?php echo Helper::escapeHtml($userIP); ?>
                    </span>
                <?php endif; ?>
            </small>
        </div>
    </footer>

    <!-- Global JSON Data for JavaScript -->
    <script>
        window.MESSAGES = <?= $viewData['global_json_strings']['messages'] ?? '{}' ?>;
        window.allFieldsData = <?= $viewData['global_json_strings']['fields'] ?? '{}' ?>;
        window.allFormulasData = <?= $viewData['global_json_strings']['formulas'] ?? '{}' ?>;
    </script>
    
    <!-- FIX: Changed CDN links to local paths -->
    <script src="<?php echo $baseUrl; ?>/js/jquery.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/autoNumeric.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/jalalidatepicker.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/toastify.js"></script>

    <script>
        // Global Initializations
        document.addEventListener('DOMContentLoaded', function() {
            // Activate all Bootstrap tooltips on the page
            try {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            } catch(e) { console.error("Footer Tooltip init failed", e); }

            // Initialize all Jalali Datepickers on the page
            try {
                if (typeof jalaliDatepicker !== 'undefined') {
                    jalaliDatepicker.startWatch({
                        selector: '.jalali-datepicker',
                        showSeconds: true,
                        showTodayBtn: true,
                        showCloseBtn: true,
                        time: true,
                        format: 'Y/m/d H:i:s',
                        months: ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"],
                        days: ["ی", "د", "س", "چ", "پ", "ج", "ش"],
                        autoClose: true,
                    });
                } else {
                     console.warn('JalaliDatePicker not found.');
                }
            } catch(e) { console.error("Footer JalaliDatePicker init failed", e); }
        });
    </script>

</body>
</html>