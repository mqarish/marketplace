<?php
require_once '../includes/init.php';
require_once 'check_admin.php';

// Define BASEPATH for included files
define('BASEPATH', true);

$page_title = 'إدارة التصنيفات';
$page_icon = 'fa-tags';

// Get categories with product count
$query = "
    SELECT c.*, COUNT(p.id) as products_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name ASC";

// Debug query
error_log("Categories query: " . $query);

$result = $conn->query($query);
if (!$result) {
    error_log("Error en la consulta de categorías: " . $conn->error);
    // Crear un resultado vacío para evitar errores
    $result = new class {
        public $num_rows = 0;
        public function fetch_assoc() { return null; }
        public function data_seek($pos) { }
    };
}

// Get statistics
$total_categories = $result->num_rows;
$active_categories = $total_categories; // Todas las categorías son activas por defecto
$inactive_categories = 0;
$total_products = 0;

// Calcular el total de productos solo si hay categorías
if ($result->num_rows > 0) {
    while ($category = $result->fetch_assoc()) {
        // Asegurarse de que products_count es un número
        $product_count = isset($category['products_count']) ? intval($category['products_count']) : 0;
        $total_products += $product_count;
    }
    // Reset result pointer
    $result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <?php include 'admin_header.php'; ?>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container-fluid">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">إجمالي التصنيفات</h5>
                        <p class="card-text display-6"><?php echo number_format($total_categories); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">التصنيفات النشطة</h5>
                        <p class="card-text display-6"><?php echo number_format($active_categories); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">التصنيفات غير النشطة</h5>
                        <p class="card-text display-6"><?php echo number_format($inactive_categories); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">إجمالي المنتجات</h5>
                        <p class="card-text display-6"><?php echo number_format($total_products); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">قائمة التصنيفات</h3>
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i>
                    إضافة تصنيف جديد
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم التصنيف</th>
                                <th>الأيقونة</th>
                                <th>الوصف</th>
                                <th>عدد المنتجات</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($category = $result->fetch_assoc()): ?>
                                    <?php if ($category): ?>
                                    <tr>
                                        <td><?php echo isset($category['id']) ? $category['id'] : ''; ?></td>
                                        <td><?php echo isset($category['name']) ? htmlspecialchars($category['name']) : ''; ?></td>
                                        <td>
                                            <?php if (!empty($category['image_url'])): ?>
                                                <img src="../uploads/categories/<?php echo htmlspecialchars($category['image_url']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" style="max-height: 40px; max-width: 40px;">
                                            <?php elseif (!empty($category['icon'])): ?>
                                                <?php if (strpos($category['icon'], 'fa-') === 0): ?>
                                                    <i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i>
                                                <?php else: ?>
                                                    <i class="bi <?php echo htmlspecialchars($category['icon']); ?>"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($category['icon']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo isset($category['description']) ? htmlspecialchars($category['description']) : ''; ?></td>
                                        <td>
                                            <span class="text-primary">
                                                <i class="fas fa-box-open me-1"></i>
                                                <?php echo isset($category['products_count']) ? number_format(intval($category['products_count'])) : '0'; ?>
                                            </span>
                                            <?php if (isset($category['products_count']) && intval($category['products_count']) > 0): ?>
                                            <a href="products.php?category=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary ms-2" title="عرض المنتجات">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                نشط
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info" onclick="editCategory(<?php echo $category['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            لا توجد تصنيفات مضافة حتى الآن. يمكنك إضافة تصنيف جديد بالنقر على زر "إضافة تصنيف جديد".
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal إضافة تصنيف جديد -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة تصنيف جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addCategoryForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="name" class="form-label">اسم التصنيف</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="icon" class="form-label">أيقونة التصنيف (اختياري)</label>
                            <input type="text" class="form-control" id="icon" name="icon" placeholder="مثال: bi-laptop أو fa-shopping-cart">
                            <small class="form-text text-muted">يمكنك استخدام أيقونات Bootstrap Icons أو Font Awesome. أدخل اسم الأيقونة فقط.</small>
                        </div>
                        <div class="mb-3 border p-3 bg-light rounded">
                            <label for="category_image" class="form-label fw-bold text-primary">صورة التصنيف <span class="badge bg-primary">مهم</span></label>
                            <input type="file" class="form-control" id="category_image" name="category_image" accept="image/*">
                            <div class="mt-2 d-flex align-items-center">
                                <i class="fas fa-info-circle text-primary me-2"></i>
                                <small class="form-text">يرجى رفع صورة للتصنيف لتظهر في صفحة العملاء. الصيغ المدعومة: JPG, PNG, GIF.</small>
                            </div>
                            <div class="form-text text-danger mt-2"><i class="fas fa-exclamation-triangle"></i> ملاحظة: الصورة ضرورية لعرض التصنيف بشكل جذاب في واجهة العميل.</div>
                        </div>
                        <!-- Campo de estado eliminado ya que no existe en la base de datos -->
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="addCategory()">إضافة</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal تعديل التصنيف -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل التصنيف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editCategoryForm" enctype="multipart/form-data">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">اسم التصنيف</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_icon" class="form-label">أيقونة التصنيف (اختياري)</label>
                            <input type="text" class="form-control" id="edit_icon" name="icon" placeholder="مثال: bi-laptop أو fa-shopping-cart">
                            <small class="form-text text-muted">يمكنك استخدام أيقونات Bootstrap Icons أو Font Awesome. أدخل اسم الأيقونة فقط.</small>
                        </div>
                        <div class="mb-3 border p-3 bg-light rounded">
                            <label for="edit_category_image" class="form-label fw-bold text-primary">صورة التصنيف <span class="badge bg-primary">مهم</span></label>
                            <input type="file" class="form-control" id="edit_category_image" name="category_image" accept="image/*">
                            <div class="mt-2 d-flex align-items-center">
                                <i class="fas fa-info-circle text-primary me-2"></i>
                                <small class="form-text">يرجى رفع صورة للتصنيف لتظهر في صفحة العملاء. يمكنك رفع صورة جديدة أو ترك هذا الحقل فارغًا للاحتفاظ بالصورة الحالية.</small>
                            </div>
                            <div class="form-text text-danger mt-2"><i class="fas fa-exclamation-triangle"></i> ملاحظة: الصورة ضرورية لعرض التصنيف بشكل جذاب في واجهة العميل.</div>
                        </div>
                        <div class="mb-3" id="current_image_container" style="display: none;">
                            <label class="form-label">الصورة الحالية</label>
                            <div class="current-image-preview">
                                <img id="current_category_image" src="" alt="صورة التصنيف" class="img-thumbnail" style="max-height: 150px;">
                            </div>
                        </div>
                        <!-- Campo de estado eliminado ya que no existe en la base de datos -->
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="updateCategory()">حفظ التغييرات</button>
                </div>
            </div>
        </div>
    </div>

    <!-- تضمين ملفات جافا سكريبت -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function addCategory() {
        // تحقق من وجود اسم التصنيف
        if (!$('#name').val().trim()) {
            alert('يرجى إدخال اسم التصنيف');
            return;
        }
        
        // إظهار مؤشر التحميل
        $('#addCategoryModal .modal-footer .btn-primary').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري الإضافة...');
        $('#addCategoryModal .modal-footer .btn-primary').prop('disabled', true);
        
        // إنشاء كائن FormData لإرسال الملفات
        var formData = new FormData($('#addCategoryForm')[0]);
        formData.append('action', 'add');
        
        $.ajax({
            url: 'handle_category.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,  // مهم لمعالجة الملفات
            contentType: false,  // مهم لمعالجة الملفات
            success: function(response) {
                console.log('Response:', response);
                if (response && response.success) {
                    alert('تم إضافة التصنيف بنجاح');
                    location.reload();
                } else {
                    var errorMsg = (response && response.message) ? response.message : 'حدث خطأ أثناء إضافة التصنيف';
                    alert(errorMsg);
                    // إعادة تعيين زر الإضافة
                    $('#addCategoryModal .modal-footer .btn-primary').html('إضافة');
                    $('#addCategoryModal .modal-footer .btn-primary').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                console.error('Status:', status);
                console.error('Response Text:', xhr.responseText);
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.message) {
                        alert(jsonResponse.message);
                    } else {
                        alert('حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
                    }
                } catch (e) {
                    alert('حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
                }
                // إعادة تعيين زر الإضافة
                $('#addCategoryModal .modal-footer .btn-primary').html('إضافة');
                $('#addCategoryModal .modal-footer .btn-primary').prop('disabled', false);
            }
        });
    }

    function editCategory(id) {
        $.get('handle_category.php', {
            action: 'get',
            id: id
        }, function(response) {
            if (response.success) {
                $('#edit_id').val(response.category.id);
                $('#edit_name').val(response.category.name);
                $('#edit_description').val(response.category.description);
                $('#edit_icon').val(response.category.icon);
                
                // عرض الصورة الحالية إذا كانت موجودة
                if (response.category.image_url) {
                    $('#current_category_image').attr('src', '../uploads/categories/' + response.category.image_url);
                    $('#current_image_container').show();
                } else {
                    $('#current_image_container').hide();
                }
                
                $('#editCategoryModal').modal('show');
            }
        });
    }

    function updateCategory() {
        // تحقق من وجود اسم التصنيف
        if (!$('#edit_name').val().trim()) {
            alert('يرجى إدخال اسم التصنيف');
            return;
        }
        
        // إظهار مؤشر التحميل
        $('#editCategoryModal .modal-footer .btn-primary').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري التحديث...');
        $('#editCategoryModal .modal-footer .btn-primary').prop('disabled', true);
        
        // إنشاء كائن FormData لإرسال الملفات
        var formData = new FormData($('#editCategoryForm')[0]);
        formData.append('action', 'update');
        
        $.ajax({
            url: 'handle_category.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,  // مهم لمعالجة الملفات
            contentType: false,  // مهم لمعالجة الملفات
            success: function(response) {
                console.log('Update Response:', response);
                if (response && response.success) {
                    alert('تم تحديث التصنيف بنجاح');
                    location.reload();
                } else {
                    var errorMsg = (response && response.message) ? response.message : 'حدث خطأ أثناء تحديث التصنيف';
                    alert(errorMsg);
                    // إعادة تعيين زر التحديث
                    $('#editCategoryModal .modal-footer .btn-primary').html('حفظ التغييرات');
                    $('#editCategoryModal .modal-footer .btn-primary').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                console.error('Status:', status);
                console.error('Response Text:', xhr.responseText);
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.message) {
                        alert(jsonResponse.message);
                    } else {
                        alert('حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
                    }
                } catch (e) {
                    alert('حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
                }
                // إعادة تعيين زر التحديث
                $('#editCategoryModal .modal-footer .btn-primary').html('حفظ التغييرات');
                $('#editCategoryModal .modal-footer .btn-primary').prop('disabled', false);
            }
        });
    }

    function deleteCategory(id) {
        if (confirm('هل أنت متأكد من حذف هذا التصنيف؟')) {
            $.post('handle_category.php', {
                action: 'delete',
                id: id
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('حدث خطأ أثناء حذف التصنيف');
                }
            });
        }
    }
    </script>

</body>
</html>
