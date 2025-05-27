<?php
/**
 * مكون شريط البحث الموحد
 * يستخدم في جميع صفحات الموقع لضمان اتساق المظهر والسلوك
 * 
 * المعلمات:
 * $form_action - مسار الصفحة التي سيتم إرسال النموذج إليها
 * $placeholder - النص الافتراضي في حقل البحث
 * $search_value - قيمة البحث الحالية (إن وجدت)
 * $hidden_fields - حقول مخفية إضافية (مصفوفة اسم => قيمة)
 * $show_type_selector - إظهار محدد نوع البحث (منتجات/متاجر)
 * $show_location - إظهار زر تحديد الموقع
 */

// تعيين القيم الافتراضية
$form_action = $form_action ?? '';
$placeholder = $placeholder ?? 'ابحث عن منتجات أو متاجر...';
$search_value = $search_value ?? '';
$hidden_fields = $hidden_fields ?? [];
$show_type_selector = $show_type_selector ?? false;
$show_location = $show_location ?? false;
$search_class = $search_class ?? 'search-form';
$input_class = $input_class ?? 'search-input';
$button_class = $button_class ?? 'round-btn';
?>

<div class="search-container">
    <form action="<?php echo htmlspecialchars($form_action); ?>" method="GET" class="<?php echo $search_class; ?>" id="search-form">
        <?php if ($show_type_selector): ?>
        <div class="search-type">
            <select name="view" class="search-select">
                <option value="products" <?php echo (isset($_GET['view']) && $_GET['view'] == 'stores') ? '' : 'selected'; ?>>المنتجات</option>
                <option value="stores" <?php echo (isset($_GET['view']) && $_GET['view'] == 'stores') ? 'selected' : ''; ?>>المتاجر</option>
            </select>
            <i class="bi bi-chevron-down"></i>
        </div>
        <?php endif; ?>
        
        <div class="search-input-wrap">
            <input type="text" name="search" class="<?php echo $input_class; ?>" 
                   placeholder="<?php echo htmlspecialchars($placeholder); ?>" 
                   value="<?php echo htmlspecialchars($search_value); ?>" 
                   autocomplete="off">
        </div>
        
        <?php foreach ($hidden_fields as $name => $value): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($value); ?>">
        <?php endforeach; ?>
        
        <?php if ($show_location): ?>
        <button type="button" class="<?php echo $button_class; ?> location-btn" onclick="getLocation()">
            <i class="bi bi-geo-alt"></i>
        </button>
        <?php else: ?>
        <button type="submit" class="<?php echo $button_class; ?>">
            <i class="bi bi-search"></i>
        </button>
        <?php endif; ?>
    </form>
</div>
