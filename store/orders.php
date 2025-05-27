<?php
session_start();
require_once '../includes/init.php';

// التحقق من تسجيل الدخول كمتجر
if (!isset($_SESSION['store_id'])) {
    header("Location: login.php");
    exit();
}

$store_id = $_SESSION['store_id'];

// استعلام لجلب بيانات المتجر
$store_query = "SELECT * FROM stores WHERE id = ?";
$store_stmt = $conn->prepare($store_query);
$store_stmt->bind_param("i", $store_id);
$store_stmt->execute();
$store_result = $store_stmt->get_result();
$store = $store_result->fetch_assoc();

// التحقق من وجود جدول الطلبات
$table_exists_query = "SHOW TABLES LIKE 'orders'";
$table_exists_result = $conn->query($table_exists_query);
$orders_table_exists = ($table_exists_result && $table_exists_result->num_rows > 0);

// تهيئة المتغيرات
$orders = [];
$total_orders = 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// جلب الطلبات
if ($orders_table_exists) {
    // بناء استعلام الطلبات
    $count_query = "SELECT COUNT(*) as total FROM orders WHERE store_id = ?";
    $orders_query = "
        SELECT 
            o.*,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
        FROM 
            orders o
        WHERE 
            o.store_id = ?
    ";
    
    // إضافة فلتر الحالة
    if ($filter_status != 'all') {
        $count_query .= " AND status = ?";
        $orders_query .= " AND o.status = ?";
    }
    
    // إضافة البحث
    if (!empty($search_term)) {
        $count_query .= " AND (id LIKE ? OR shipping_address LIKE ? OR phone LIKE ?)";
        $orders_query .= " AND (o.id LIKE ? OR o.shipping_address LIKE ? OR o.phone LIKE ?)";
    }
    
    // إضافة الترتيب والصفحات
    $orders_query .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
    
    // تنفيذ استعلام العدد الإجمالي
    $count_stmt = $conn->prepare($count_query);
    
    if ($filter_status != 'all' && !empty($search_term)) {
        $search_param = "%$search_term%";
        $count_stmt->bind_param("issss", $store_id, $filter_status, $search_param, $search_param, $search_param);
    } elseif ($filter_status != 'all') {
        $count_stmt->bind_param("is", $store_id, $filter_status);
    } elseif (!empty($search_term)) {
        $search_param = "%$search_term%";
        $count_stmt->bind_param("isss", $store_id, $search_param, $search_param, $search_param);
    } else {
        $count_stmt->bind_param("i", $store_id);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_orders = $count_row['total'];
    $total_pages = ceil($total_orders / $per_page);
    
    // تنفيذ استعلام الطلبات
    $orders_stmt = $conn->prepare($orders_query);
    
    if ($filter_status != 'all' && !empty($search_term)) {
        $search_param = "%$search_term%";
        $orders_stmt->bind_param("isssssii", $store_id, $filter_status, $search_param, $search_param, $search_param, $per_page, $offset);
    } elseif ($filter_status != 'all') {
        $orders_stmt->bind_param("isii", $store_id, $filter_status, $per_page, $offset);
    } elseif (!empty($search_term)) {
        $search_param = "%$search_term%";
        $orders_stmt->bind_param("issssii", $store_id, $search_param, $search_param, $search_param, $per_page, $offset);
    } else {
        $orders_stmt->bind_param("iii", $store_id, $per_page, $offset);
    }
    
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    
    while ($order = $orders_result->fetch_assoc()) {
        $orders[] = $order;
    }
} else {
    // إذا لم يكن جدول الطلبات موجوداً، نعرض رسالة للمستخدم
    $total_orders = 0;
    $total_pages = 1;
}

// دالة لتحويل حالة الطلب إلى اللغة العربية
function getOrderStatusInArabic($status) {
    $statuses = [
        'pending' => 'قيد الانتظار',
        'processing' => 'قيد التجهيز',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التوصيل',
        'cancelled' => 'ملغي'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

// دالة لتحويل طريقة الدفع إلى اللغة العربية
function getPaymentMethodInArabic($method) {
    $methods = [
        'cash_on_delivery' => 'الدفع عند الاستلام',
        'credit_card' => 'بطاقة ائتمان',
        'bank_transfer' => 'تحويل بنكي'
    ];
    
    return isset($methods[$method]) ? $methods[$method] : $method;
}

// دالة لتحويل حالة الدفع إلى اللغة العربية
function getPaymentStatusInArabic($status) {
    $statuses = [
        'pending' => 'قيد الانتظار',
        'paid' => 'تم الدفع',
        'failed' => 'فشل الدفع'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

// دالة للحصول على لون الحالة
function getStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger'
    ];
    
    return isset($colors[$status]) ? $colors[$status] : 'secondary';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الطلبات - لوحة تحكم المتجر</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-color: #f3f4f6;
            --dark-color: #1f2937;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .page-header {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .breadcrumb {
            margin-bottom: 0;
        }
        
        .dashboard-card {
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #eee;
            padding: 1rem 1.5rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .order-item {
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-number {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .order-date {
            font-size: 0.85rem;
            color: #666;
        }
        
        .order-customer {
            font-weight: 500;
        }
        
        .order-total {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .order-actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }
        
        .filter-bar {
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-bar .form-select,
        .filter-bar .form-control {
            border-color: #eee;
        }
        
        .pagination {
            margin-bottom: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/store_navbar.php'; ?>
    
    <!-- الشريط العلوي مع مسار التنقل -->
    <div class="page-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">لوحة التحكم</a></li>
                    <li class="breadcrumb-item active" aria-current="page">إدارة الطلبات</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="container py-4">
        <!-- شريط الفلترة والبحث -->
        <div class="filter-bar">
            <form action="" method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="بحث برقم الطلب، العنوان، أو رقم الهاتف" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>جميع الحالات</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                        <option value="processing" <?php echo $filter_status == 'processing' ? 'selected' : ''; ?>>قيد التجهيز</option>
                        <option value="shipped" <?php echo $filter_status == 'shipped' ? 'selected' : ''; ?>>تم الشحن</option>
                        <option value="delivered" <?php echo $filter_status == 'delivered' ? 'selected' : ''; ?>>تم التوصيل</option>
                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                    </select>
                </div>
                <div class="col-md-5 text-md-end">
                    <span class="text-muted">إجمالي الطلبات: <?php echo $total_orders; ?></span>
                </div>
            </form>
        </div>
        
        <!-- قائمة الطلبات -->
        <div class="dashboard-card">
            <div class="card-header">
                <span>الطلبات</span>
            </div>
            
            <?php if ($orders_table_exists && count($orders) > 0): ?>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>التاريخ</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الدفع</th>
                                    <th>حالة الدفع</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <span class="order-number">#<?php echo $order['id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="order-date">
                                                <?php echo date('d/m/Y', strtotime($order['created_at'])); ?>
                                                <div class="small text-muted">
                                                    <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="order-total">
                                                <?php echo number_format($order['total_amount'], 2); ?> ريال
                                            </span>
                                            <div class="small text-muted">
                                                <?php echo $order['items_count']; ?> منتج
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getPaymentMethodInArabic($order['payment_method']); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge bg-<?php echo $order['payment_status'] == 'paid' ? 'success' : ($order['payment_status'] == 'failed' ? 'danger' : 'warning'); ?>">
                                                <?php echo getPaymentStatusInArabic($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge bg-<?php echo getStatusColor($order['status']); ?>">
                                                <?php echo getOrderStatusInArabic($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="order-actions">
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> عرض
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#updateStatusModal" data-order-id="<?php echo $order['id']; ?>" data-order-status="<?php echo $order['status']; ?>">
                                                    <i class="bi bi-arrow-repeat"></i> تحديث
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ترقيم الصفحات -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search_term); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php elseif (!$orders_table_exists): ?>
                <div class="empty-state">
                    <i class="bi bi-exclamation-circle"></i>
                    <h4>جداول الطلبات غير موجودة</h4>
                    <p>يبدو أن جداول الطلبات غير موجودة في قاعدة البيانات. يرجى تشغيل ملف إنشاء جداول الطلبات أولاً.</p>
                    <a href="../create_orders_tables.php" class="btn btn-primary">إنشاء جداول الطلبات</a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-bag-x"></i>
                    <h4>لا توجد طلبات</h4>
                    <p>لم يتم العثور على أي طلبات تطابق معايير البحث الخاصة بك.</p>
                    <?php if ($filter_status != 'all' || !empty($search_term)): ?>
                        <a href="orders.php" class="btn btn-primary">عرض جميع الطلبات</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة تحديث حالة الطلب -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">تحديث حالة الطلب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="update-order-status.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="orderIdInput">
                        <div class="mb-3">
                            <label for="orderStatus" class="form-label">الحالة الجديدة</label>
                            <select class="form-select" id="orderStatus" name="status" required>
                                <option value="pending">قيد الانتظار</option>
                                <option value="processing">قيد التجهيز</option>
                                <option value="shipped">تم الشحن</option>
                                <option value="delivered">تم التوصيل</option>
                                <option value="cancelled">ملغي</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="statusNotes" class="form-label">ملاحظات (اختياري)</label>
                            <textarea class="form-control" id="statusNotes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">تحديث الحالة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تحديث بيانات نافذة تحديث الحالة
        document.addEventListener('DOMContentLoaded', function() {
            const updateStatusModal = document.getElementById('updateStatusModal');
            if (updateStatusModal) {
                updateStatusModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const orderId = button.getAttribute('data-order-id');
                    const orderStatus = button.getAttribute('data-order-status');
                    
                    const orderIdInput = document.getElementById('orderIdInput');
                    const orderStatusSelect = document.getElementById('orderStatus');
                    
                    orderIdInput.value = orderId;
                    orderStatusSelect.value = orderStatus;
                });
            }
        });
    </script>
</body>
</html>
