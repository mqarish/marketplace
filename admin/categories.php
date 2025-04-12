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
$result = $conn->query($query);

// Get statistics
$total_categories = $result->num_rows;
$active_categories = 0;
$inactive_categories = 0;
$total_products = 0;

if ($result->num_rows > 0) {
    while ($category = $result->fetch_assoc()) {
        // Set default status if not set
        $status = isset($category['status']) ? $category['status'] : 'inactive';
        if ($status === 'active') {
            $active_categories++;
        } else {
            $inactive_categories++;
        }
        $total_products += $category['products_count'];
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
                                <th>الوصف</th>
                                <th>عدد المنتجات</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($category = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                                    <td><?php echo $category['products_count']; ?></td>
                                    <td>
                                        <?php 
                                        $status = isset($category['status']) ? $category['status'] : 'inactive';
                                        $badge_class = $status === 'active' ? 'success' : 'danger';
                                        $status_text = $status === 'active' ? 'نشط' : 'غير نشط';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo $status_text; ?>
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
                            <?php endwhile; ?>
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
                    <form id="addCategoryForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">اسم التصنيف</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">الحالة</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">نشط</option>
                                <option value="inactive">معطل</option>
                            </select>
                        </div>
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
                    <form id="editCategoryForm">
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
                            <label for="edit_status" class="form-label">الحالة</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">نشط</option>
                                <option value="inactive">معطل</option>
                            </select>
                        </div>
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
        $.post('handle_category.php', {
            action: 'add',
            name: $('#name').val(),
            description: $('#description').val(),
            status: $('#status').val()
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('حدث خطأ أثناء إضافة التصنيف');
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
                $('#edit_status').val(response.category.status);
                $('#editCategoryModal').modal('show');
            }
        });
    }

    function updateCategory() {
        $.post('handle_category.php', {
            action: 'update',
            id: $('#edit_id').val(),
            name: $('#edit_name').val(),
            description: $('#edit_description').val(),
            status: $('#edit_status').val()
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('حدث خطأ أثناء تحديث التصنيف');
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
