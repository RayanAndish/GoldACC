<?php
$messages_from_php_if_any = $messages_from_php_if_any ?? [];
$fieldsFromView = $viewData['fields'] ?? [];
$formulas = $viewData['formulas'] ?? [];
$products_list = $viewData['products_list'] ?? []; // این باید آرایه‌ای از آبجکت‌های Product باشد
$contacts = $viewData['contacts'] ?? [];   // این باید آرایه‌ای از آبجکت‌های Contact باشد
$baseUrl = rtrim($viewData['baseUrl'] ?? '', '/');
$pageTitle = $viewData['page_title'] ?? 'فرم معامله';
$errorMessage = $viewData['error_message'] ?? null;
$loadingError = $viewData['loading_error'] ?? null;
$csrfToken = $viewData['csrf_token'] ?? ''; // CSRF token for security

// گروه‌بندی فیلدها برای transaction
$fieldGroups = [
    'main' => [1, 2, 3, 4, 201], 
    'item_row' => [5],      // items[{index}][product_id]
    'notes' => [6],         // notes
];

// تابع کمکی برای پیدا کردن فیلد با ID
function findFieldById(array $fieldsArray, int $id): ?array {
    foreach ($fieldsArray as $field) {
        if (isset($field['id']) && $field['id'] === $id) {
            return $field;
        }
    }
    return null;
}

// داده‌های احتمالی برای حالت ویرایش (فعلا خالی در نظر گرفته می‌شود برای فرم افزودن)
$transactionData = $viewData['transaction'] ?? []; // می‌تواند آبجکت یا آرایه باشد
$transactionItemsData = $viewData['transaction_items'] ?? [];

?>
<div class="container mt-4">
    <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    
    <!-- نمایش پیام خطا -->
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <?php 
            if (is_array($errorMessage) && isset($errorMessage['text'])) {
                echo $errorMessage['text'];
            } else {
                echo $errorMessage;
            }
            ?>
        </div>
    <?php endif; ?>
    
    <?php if ($loadingError): ?>
        <div class="alert alert-warning"><?= $loadingError ?></div>
    <?php endif; ?>
    
    <?php if (isset($viewData['success_message']) && !empty($viewData['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            if (is_array($viewData['success_message']) && isset($viewData['success_message']['text'])) {
                echo $viewData['success_message']['text'];
            } else {
                echo $viewData['success_message'];
            }
            ?>
        </div>
    <?php endif; ?>
<form id="transaction-form" class="p-4 needs-validation" action="<?= htmlspecialchars($viewData['form_action'] ?? '', ENT_QUOTES, 'UTF-8') ?>" method="POST" novalidate>
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <!-- فیلدهای بالای فرم -->
  <div class="row mb-3">
  <?php
  foreach ($fieldGroups['main'] as $fid):
    $f = findFieldById($fieldsFromView, $fid);
    if ($f):
      $fieldName = $f['name'] ?? 'unknown_field_' . $fid;
      $fieldLabel = $f['label'] ?? 'فیلد ناشناس';
      $fieldType = $f['type'] ?? 'text';
      $fieldValue = $transactionData[$fieldName] ?? '';
      // $colClass = $f['col_class'] ?? 'col-md-3'; // این خط را حذف کن
  ?>
      <div class="col-md-2">
        <label for="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" class="form-label"><?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?><?= $fieldType !== 'textarea' ? ' <span class="text-danger">*</span>' : '' ?></label>
        <?php if ($fieldType === 'select' && $fieldName === 'counterparty_contact_id'): ?>
          <select id="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" class="form-select" required>
            <option value="">انتخاب طرف حساب...</option>
            <?php if (!empty($contacts)): foreach ($contacts as $contact):
                $contactId = $contact['id'] ?? null;
                $contactName = $contact['name'] ?? 'نامشخص';
                $isSelected = (isset($transactionData['counterparty_contact_id']) && $transactionData['counterparty_contact_id'] == $contactId);?>
                <option value="<?= htmlspecialchars((string)$contactId, ENT_QUOTES, 'UTF-8') ?>" <?= $isSelected ? 'selected' : '' ?>>
                    <?= htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8') ?>
                </option>            
              <?php endforeach; endif; ?>
          </select>
        <?php elseif ($fieldType === 'select' && $fieldName === 'transaction_type'): 
            $currentTransactionType = $transactionData['transaction_type'] ?? 'buy';
        ?>
          <select id="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" class="form-select" required>
            <option value="buy" <?= ($currentTransactionType === 'buy') ? 'selected' : '' ?>>خرید</option>
            <option value="sell" <?= ($currentTransactionType === 'sell') ? 'selected' : '' ?>>فروش</option>
          </select>
        <?php elseif ($fieldType === 'select' && $fieldName === 'delivery_status'): ?>
          <select id="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" class="form-select" required>
            <option value="">انتخاب وضعیت...</option>
            <?php foreach (($f['options'] ?? []) as $opt): ?>
              <option value="<?= htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') ?>" <?= ($fieldValue == $opt['value']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php elseif ($fieldName === 'mazaneh_price'): ?>
          <input 
            type="<?= htmlspecialchars($fieldType, ENT_QUOTES, 'UTF-8') ?>" 
            id="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" 
            name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" 
            class="form-control autonumeric" 
            value="<?= htmlspecialchars((string)$fieldValue, ENT_QUOTES, 'UTF-8') ?>"
            data-autonumeric-options='{"digitGroupSeparator": "٬", "decimalPlaces": 0, "selectOnFocus": false}'
            required>
        <?php else: // text, number, date, etc.
            $inputClass = 'form-control';
            if ($fieldName === 'transaction_date') $inputClass .= ' jalali-datepicker';
            if ($f['is_numeric'] ?? false) $inputClass .= ' autonumeric';
            $dataAutonumeric = '';
            if ($f['is_numeric'] ?? false) {
                $dataAutonumeric = 'data-autonumeric-options=\'{\"digitGroupSeparator\": \"٬\"}\'';
            }
        ?>
          <input 
            type="<?= htmlspecialchars($fieldType, ENT_QUOTES, 'UTF-8') ?>" 
            id="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" 
            name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" 
            class="<?= $inputClass ?>" 
            value="<?= htmlspecialchars((string)$fieldValue, ENT_QUOTES, 'UTF-8') ?>"
            <?= $dataAutonumeric ?>
            required>
        <?php endif; ?>
        <div class="invalid-feedback">لطفا <?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?> را وارد کنید.</div>
      </div>
    <?php endif; endforeach; ?>
  </div>

  <!-- بخش شرح معامله -->
  <h5 class="mt-4 mb-3">اقلام معامله <span class="text-danger">*</span></h5>
  <div id="transaction-items-container" class="mb-3">
    <?php
    foreach ($transactionItemsData as $index => $item) {
        $group = '';
        if (!empty($item['product_id']) && isset($products_list)) {
            foreach ($products_list as $p) {
                if ($p->id == $item['product_id']) {
                    $group = strtolower($p->category->base_category ?? '');
                    break;
                }
            }
        }
        // فیلدهای داینامیک مالیات و ارزش افزوده برای هر گروه
        $taxField = '';
        $vatField = '';
        switch ($group) {
            case 'melted':
                $taxField = 'item_general_tax_melted';
                $vatField = 'item_vat_melted';
                break;
            case 'manufactured':
                $taxField = 'item_general_tax_manufactured';
                $vatField = 'item_vat_manufactured';
                break;
            case 'coin':
                $taxField = 'item_general_tax_coin';
                $vatField = 'item_vat_coin';
                break;
            case 'goldbullion':
                $taxField = 'item_general_tax_goldbullion';
                $vatField = 'item_vat_goldbullion';
                break;
            case 'silverbullion':
                $taxField = 'item_general_tax_silverbullion';
                $vatField = 'item_vat_silverbullion';
                break;
            case 'jewelry':
                $taxField = 'item_general_tax_jewelry';
                $vatField = 'item_vat_jewelry';
                break;
        }
        if ($taxField) {
            echo '<input type="hidden" name="items['.$index.']['.$taxField.']" value="'.htmlspecialchars($item[$taxField] ?? '').'">';
        }
        if ($vatField) {
            echo '<input type="hidden" name="items['.$index.']['.$vatField.']" value="'.htmlspecialchars($item[$vatField] ?? '').'">';
        }
    }
    ?>
  </div>
  <div class="d-flex justify-content-between my-3">
    <button type="button" id="add-transaction-item" class="btn btn-success"> افزودن قلم جدید</button>
  </div>

  <!-- قالب ردیف کالا -->
  <template id="item-row-template">
    <div class="transaction-item-row border rounded p-3 mb-3 bg-light position-relative">
      <!-- ردیف اول: انتخاب کالا با عرض کامل -->
      <div class="row mb-2">
        <div class="col-12">
          <label class="form-label">کالا <span class="text-danger">*</span></label>
          <select class="form-select product-select" name="items[{index}][product_id]" required>
            <!-- گزینه‌ها توسط جاوااسکریپت (fillProductSelect) پر خواهند شد -->
            <option value="">انتخاب کالا...</option>
          </select>
          <div class="invalid-feedback">لطفا کالا را انتخاب کنید.</div>
        </div>
      </div>
      <!-- ردیف دوم: فیلدهای اختصاصی کالا با عرض کامل -->
      <div class="row mb-2">
        <div class="col-12">
          <div class="row dynamic-fields-row" id="dynamic-fields-{index}">
            <!-- Dynamic fields based on product selection will be injected here by JS -->
          </div>
        </div>
      </div>
      <!-- فیلدهای hidden مالیات و ارزش افزوده برای همه گروه‌ها -->
      <input type="hidden" name="items[{index}][item_general_tax_melted]" value="">
      <input type="hidden" name="items[{index}][item_vat_melted]" value="">
      <input type="hidden" name="items[{index}][item_general_tax_manufactured]" value="">
      <input type="hidden" name="items[{index}][item_vat_manufactured]" value="">
      <input type="hidden" name="items[{index}][item_general_tax_coin]" value="">
      <input type="hidden" name="items[{index}][item_vat_coin]" value="">
      <input type="hidden" name="items[{index}][item_general_tax_goldbullion]" value="">
      <input type="hidden" name="items[{index}][item_vat_goldbullion]" value="">
      <input type="hidden" name="items[{index}][item_general_tax_silverbullion]" value="">
      <input type="hidden" name="items[{index}][item_vat_silverbullion]" value="">
      <input type="hidden" name="items[{index}][item_general_tax_jewelry]" value="">
      <input type="hidden" name="items[{index}][item_vat_jewelry]" value="">
      <!-- ردیف سوم: دکمه حذف -->
      <div class="row">
        <div class="col-12 text-end">
          <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn ms-2">
            <i class="bi bi-trash"></i> حذف ردیف
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- بخش خلاصه مالی -->
  <div class="card mt-4">
    <div class="card-header bg-info text-white">خلاصه مالی</div>
    <div class="card-body">
      <div class="row mb-2">
        <div class="col-md-4">جمع پایه اقلام (ریال): <span id="summary-sum_base_items">۰</span></div>
        <div class="col-md-4">جمع سود/اجرت/کارمزد (ریال): <span id="summary-sum_profit_wage_fee">۰</span></div>
        <div class="col-md-4">جمع مالیات عمومی (ریال): <span id="summary-total_general_tax">۰</span></div>
      </div>
      <div class="row mb-2">
        <div class="col-md-4">جمع قبل از ارزش افزوده (ریال): <span id="summary-sum_before_vat">۰</span></div>
        <div class="col-md-4">مالیات بر ارزش افزوده کل (ریال): <span id="summary-total_vat">۰</span></div>
        <div class="col-md-4">مبلغ نهایی قابل پرداخت (ریال): <span id="summary-final_payable">۰</span></div>
      </div>
    </div>
  </div>

  <div class="mt-4">
    <?php 
      $notesField = findFieldById($fieldsFromView, 6); // Field ID for notes
      if ($notesField):
        $notesFieldName = $notesField['name'] ?? 'notes';
        $notesFieldLabel = $notesField['label'] ?? 'یادداشت‌ها';
        $notesFieldValue = $transactionData[$notesFieldName] ?? '';
    ?>
      <label for="<?= htmlspecialchars($notesFieldName, ENT_QUOTES, 'UTF-8') ?>" class="form-label"><?= htmlspecialchars($notesFieldLabel, ENT_QUOTES, 'UTF-8') ?></label>
      <textarea id="<?= htmlspecialchars($notesFieldName, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($notesFieldName, ENT_QUOTES, 'UTF-8') ?>" rows="3" class="form-control"><?= htmlspecialchars($notesFieldValue, ENT_QUOTES, 'UTF-8') ?></textarea>
    <?php endif; ?>
    <div class="mt-4 text-center">
        <button type="submit" class="btn btn-primary btn-lg">💾 ثبت معامله</button>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/app/transactions" class="btn btn-secondary btn-lg">انصراف</a>
    </div>
  </div>
</form>
</div> <!-- End container -->

<script>
    window.productsData = <?= json_encode($products_list ?? []) ?>;
    window.baseUrl = "<?= htmlspecialchars($baseUrl ?? '', ENT_QUOTES, 'UTF-8') ?>";
    window.MESSAGES = <?= json_encode($messages_from_php_if_any ?? []) ?>;
    window.allFieldsData = {fields: <?= json_encode(array_values($fieldsFromView ?? [])) ?>};
    window.allFormulasData = {formulas: <?= json_encode(array_values($formulas ?? [])) ?>};
    // Pass existing transaction items data to JavaScript for rendering
    window.transactionItemsData = <?php echo json_encode($transactionItemsData, JSON_UNESCAPED_UNICODE); ?>;
    window.assayOfficesData = <?php echo json_encode($viewData['assay_offices'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
    window.categoryIdToBaseCategory = {
      20: 'melted',
      21: 'coin',         // کد 21 مربوط به سکه است
      22: 'manufactured', // کد 22 مربوط به مصنوعات طلا (ساخته شده) است
      23: 'goldbullion',
      27: 'jewelry',
      28: 'silverbullion'
      // سایر دسته‌ها را در صورت نیاز اضافه کنید
    };
</script>
<script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/js/transaction-form.js"></script>

<script>
  // Bootstrap form validation
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>