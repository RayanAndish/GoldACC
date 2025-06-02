<?php
/**
 * Template: src/views/layouts/footer.php
 * Main application footer and closing HTML tags.
 * Included by ViewRenderer when $withLayout is true.
 * Receives data via $viewData array.
 */

use App\Utils\Helper; // Use the Helper class if needed directly here

// --- Extract common data from $viewData with defaults ---
$appName = $viewData['appName'] ?? 'حسابداری رایان طلا';
$baseUrl = $viewData['baseUrl'] ?? '';
$isLoggedIn = $viewData['isLoggedIn'] ?? false; // Maybe needed for conditional footer content

// Get IP and Ray ID (can be retrieved again or passed from header data)
$userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if ($userIP === '::1') $userIP = '127.0.0.1'; // Standardize localhost IP
$rayId = null;
if (session_status() == PHP_SESSION_ACTIVE && isset($_SESSION['ray_id'])) {
    $rayId = $_SESSION['ray_id'];
}

?>

    <?php // --- Closing the <main> tag opened in header.php --- ?>
    </main>

    <?php // --- Footer (Stays at the bottom) --- ?>
    <footer class="footer mt-auto py-3 bg-dark text-white-50 border-top border-secondary-subtle shadow-sm"> <?php /* Changed text-light-emphasis to text-white-50 for better contrast */ ?>
        <div class="container text-center">
            <span> <?php /* Removed text-muted */ ?>
                <small>
                    © <?php echo date('Y'); ?> <?php echo Helper::escapeHtml($appName); ?> | تمامی حقوق محفوظ است.
                    <span class="mx-2 d-none d-md-inline">|</span><br class="d-md-none">
                     <a href="https://www.rar-co.ir" target="_blank" class="text-decoration-none text-warning fw-bold">شرکت رایان اندیش رشد</a> <?php /* Kept text-warning, added fw-bold */ ?>
                </small>
                <small class="tech-info d-block mt-1 text-white-50"> <?php /* Ensure block display and light color */ ?>
                     <span class="me-2">IP: <?php echo Helper::escapeHtml($userIP); ?></span>
                    <?php if (!empty($rayId)):
                        ?>
                        <span class="mx-1">|</span> <span>Ray ID: <?php echo Helper::escapeHtml($rayId); ?></span>
                    <?php endif; ?>
                     <span class="mx-1">|</span> <span>مرورگر: <span id="footerBrowserInfo">...</span></span>
                </small>
            </span>
        </div>
    </footer>

    <?php // --- JavaScript Includes at the end of body --- ?>
    <?php // Bootstrap Bundle (includes Popper) - Use CDN or local ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php // Jalali Datepicker JS ?>
    <script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/jalalidatepicker.min.js"></script> <?php // فایل تاریخ شمسی ?>
    <script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/autoNumeric.min.js"></script> <?php // فایل پیام‌ها برای ترجمه ?>
    <script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/js/messages.js"></script>

    <script>
        // --- تزریق داده‌های گلوبال Fields و Formulas ---
        <?php
        $globalJsonData = $viewData['global_json_strings_for_footer'] ?? null;
        // Log the raw PHP data received by the footer

        $fieldsJsonToInject = 'null'; // Default to string 'null'
        $formulasJsonToInject = 'null'; // Default to string 'null'
        $jsonLoadError = null;

        if (is_array($globalJsonData)) {
            $fieldsJsonToInject = $globalJsonData['fields'] ?? 'null';
            $formulasJsonToInject = $globalJsonData['formulas'] ?? 'null';
            $jsonLoadError = $globalJsonData['error'] ?? null;
        } else {
            echo "console.warn('Footer Script: global_json_strings_for_footer was not an array or was null.');\n";
        }
        
         ?>

        window.allFieldsData = null; // Initialize
        window.allFormulasData = null; // Initialize
        window.baseurl = "<?= htmlspecialchars($baseUrl ?? '', ENT_QUOTES, 'UTF-8') ?>";
        let parseErrorFields = null;
        let parseErrorFormulas = null;

        try {
            // Ensure the string being parsed is valid JSON or the string 'null'
            let rawFields = <?= $fieldsJsonToInject; ?>;
            if (typeof rawFields === 'string' && rawFields.toLowerCase() === 'null') {
                 window.allFieldsData = null; // Explicitly set to null if PHP sent 'null' string
            } else if (typeof rawFields === 'object' && rawFields !== null) {
                 window.allFieldsData = rawFields; // If it's already an object (might happen if not properly json_encoded string)
            } else {
                 window.allFieldsData = JSON.parse(rawFields);
            }
        } catch (e) {
            parseErrorFields = e.message;
            window.allFieldsData = { fields: [] }; // Fallback to empty structure
        }

        try {
            let rawFormulas = <?= $formulasJsonToInject; ?>;
            if (typeof rawFormulas === 'string' && rawFormulas.toLowerCase() === 'null') {
                window.allFormulasData = null;
            } else if (typeof rawFormulas === 'object' && rawFormulas !== null) {
                window.allFormulasData = rawFormulas;
            } else {
                window.allFormulasData = JSON.parse(rawFormulas);
            }
        } catch (e) {
            parseErrorFormulas = e.message;
            console.error("Footer Script: Error parsing injected formulas.json:", e, <?= $formulasJsonToInject; ?>);
            window.allFormulasData = { formulas: [] }; // Fallback to empty structure
        }

        <?php if ($jsonLoadError): ?>
            console.warn("Footer Script: PHP error during JSON config load from index.php: <?php echo addslashes($jsonLoadError); ?>");
        <?php endif; ?>
        if(parseErrorFields) {
            console.error("Footer Script: JS Parse Error for Fields: " + parseErrorFields);
        }
        if(parseErrorFormulas) {
            console.error("Footer Script: JS Parse Error for Formulas: " + parseErrorFormulas);
        }

    </script>
    <?php // --- Global Custom JavaScript --- ?>
    <script>
        // Execute scripts after the DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function () {

            // --- Browser Detection ---
            try {
                const userAgent = navigator.userAgent || navigator.vendor || window.opera;
                let browserInfo = "ناشناخته";
                if (/chrome/i.test(userAgent) && !/edg/i.test(userAgent)) browserInfo = "گوگل کروم";
                else if (/firefox/i.test(userAgent)) browserInfo = "فایرفاکس";
                else if (/safari/i.test(userAgent) && !/chrome/i.test(userAgent)) browserInfo = "سافاری";
                else if (/msie|trident/i.test(userAgent)) browserInfo = "اینترنت اکسپلورر";
                else if (/edg/i.test(userAgent)) browserInfo = "مایکروسافت اج (Chromium)";
                else if (/edge/i.test(userAgent)) browserInfo = "مایکروسافت اج (EdgeHTML)";

                const browserInfoSpan = document.getElementById("footerBrowserInfo");
                if(browserInfoSpan) browserInfoSpan.textContent = browserInfo;
            } catch(e) { console.error('Footer Browser detect failed', e); }

            // --- Bootstrap Tooltip Initialization ---
            try {
                if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip === 'function') {
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function(tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                    // console.debug('Tooltips initialized globally.');
                } else {
                     console.warn('Bootstrap Tooltip component not found.');
                }
            } catch(e) { console.error('Footer Tooltip init failed', e); }

            // --- Jalali Date Picker Initialization ---
            try {
                 if (typeof jalaliDatepicker !== 'undefined') {
                    jalaliDatepicker.startWatch({
                        selector: '.jalali-datepicker',
                        persianDigits: false,
                        showSeconds: true, // Show seconds for datetime inputs
                        showTodayBtn: true,
                        showCloseBtn: true,
                        time: true, // Enable time picker
                        format: 'Y/m/d H:i:s', // Default format
                        months: ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"],
                        days: ["ی", "د", "س", "چ", "پ", "ج", "ش"],
                        autoClose: true, // <<< Ensure this is added/set to true
                        // Add other options as needed
                    });
                    // console.debug('JalaliDatePicker initialized globally.');
                } else {
                     console.warn('JalaliDatePicker not found.');
                }
            } catch(e) { console.error("Footer JalaliDatePicker init failed", e); }

            
        }); // End DOMContentLoaded

        // مقداردهی اولیه فیلدهای تاریخ بر اساس data-jdp-initial-value
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[data-jdp-initial-value]').forEach(function(input) {
                var initialValue = input.getAttribute('data-jdp-initial-value');
                if (initialValue && !input.value) {
                    input.value = initialValue;
                }
            });
        });
    </script>

</body>
</html>