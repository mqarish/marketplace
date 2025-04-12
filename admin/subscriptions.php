<?php
require_once '../includes/init.php';
require_once 'check_admin.php';

// Define BASEPATH for included files
define('BASEPATH', true);

$page_title = 'إدارة الاشتراكات';
$page_icon = 'fa-credit-card';

// جلب إحصائيات الاشتراكات
$stats = [
    'total' => 0,
    'active' => 0,
    'expired' => 0,
    'cancelled' => 0
];

try {
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM subscriptions";
    
    $result = $conn->query($sql);
    if ($result) {
        $stats = $result->fetch_assoc();
    }
} catch (Exception $e) {
    // Log error silently
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <?php include 'admin_header.php'; ?>
    <style>
        .table-actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .subscription-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        .status-active {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-expired {
            background-color: #f8d7da;
            color: #842029;
        }
        .status-cancelled {
            background-color: #e2e3e5;
            color: #41464b;
        }
        .stats-card {
            border: none;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .stats-card .stats-number {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stats-card .stats-text {
            font-size: 14px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card bg-primary text-white">
                    <i class="fas fa-chart-line fa-2x"></i>
                    <div class="stats-number"><?php echo number_format($stats['total']); ?></div>
                    <div class="stats-text">إجمالي الاشتراكات</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-success text-white">
                    <i class="fas fa-check-circle fa-2x"></i>
                    <div class="stats-number"><?php echo number_format($stats['active']); ?></div>
                    <div class="stats-text">الاشتراكات النشطة</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-danger text-white">
                    <i class="fas fa-times-circle fa-2x"></i>
                    <div class="stats-number"><?php echo number_format($stats['expired']); ?></div>
                    <div class="stats-text">الاشتراكات المنتهية</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-secondary text-white">
                    <i class="fas fa-ban fa-2x"></i>
                    <div class="stats-number"><?php echo number_format($stats['cancelled']); ?></div>
                    <div class="stats-text">الاشتراكات الملغاة</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo $page_title; ?></h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubscriptionModal">
                        <i class="fas fa-plus me-1"></i>
                        إضافة اشتراك جديد
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="subscriptionsTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>نوع المشترك</th>
                                <th>المشترك</th>
                                <th>الباقة</th>
                                <th>تاريخ البداية</th>
                                <th>تاريخ النهاية</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Forms -->
    <div class="modal fade" id="addSubscriptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة اشتراك جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addSubscriptionForm">
                        <div class="mb-3">
                            <label class="form-label">نوع المشترك</label>
                            <select class="form-select" name="subscriber_type" required>
                                <option value="">اختر النوع</option>
                                <option value="store">متجر</option>
                                <option value="customer">عميل</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">المشترك</label>
                            <select class="form-select" name="subscriber_id" required>
                                <option value="">اختر المشترك</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الباقة</label>
                            <select class="form-select" name="package_id" required>
                                <option value="">اختر الباقة</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاريخ البداية</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاريخ النهاية</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" id="saveSubscription">حفظ</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // تهيئة DataTables
            var table = $('#subscriptionsTable').DataTable({
                ajax: {
                    url: 'get_subscriptions.php',
                    type: 'GET'
                },
                columns: [
                    { data: 'id' },
                    { data: 'subscriber_type' },
                    { data: 'subscriber_name' },
                    { data: 'package_name' },
                    { data: 'start_date' },
                    { data: 'end_date' },
                    { 
                        data: 'status',
                        render: function(data) {
                            let className = 'status-' + data.toLowerCase();
                            return '<span class="subscription-status ' + className + '">' + data + '</span>';
                        }
                    },
                    {
                        data: null,
                        render: function(data) {
                            return `
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-primary" onclick="editSubscription(${data.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="deleteSubscription(${data.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>`;
                        }
                    }
                ],
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
                },
                responsive: true
            });

            // تحديث قائمة المشتركين عند تغيير النوع
            $('select[name="subscriber_type"]').change(function() {
                let type = $(this).val();
                if (type) {
                    $.get('get_subscribers.php', { type: type }, function(data) {
                        let options = '<option value="">اختر المشترك</option>';
                        data.forEach(function(item) {
                            options += `<option value="${item.id}">${item.name}</option>`;
                        });
                        $('select[name="subscriber_id"]').html(options);
                    });
                }
            });

            // حفظ الاشتراك الجديد
            $('#saveSubscription').click(function() {
                let formData = $('#addSubscriptionForm').serialize();
                $.post('save_subscription.php', formData, function(response) {
                    if (response.success) {
                        $('#addSubscriptionModal').modal('hide');
                        table.ajax.reload();
                        alert('تم حفظ الاشتراك بنجاح');
                    } else {
                        alert('حدث خطأ: ' + response.message);
                    }
                });
            });
        });

        // دالة تعديل الاشتراك
        function editSubscription(id) {
            // تنفيذ عملية التعديل
        }

        // دالة حذف الاشتراك
        function deleteSubscription(id) {
            if (confirm('هل أنت متأكد من حذف هذا الاشتراك؟')) {
                $.post('delete_subscription.php', { id: id }, function(response) {
                    if (response.success) {
                        $('#subscriptionsTable').DataTable().ajax.reload();
                        alert('تم حذف الاشتراك بنجاح');
                    } else {
                        alert('حدث خطأ: ' + response.message);
                    }
                });
            }
        }
    </script>
</body>
</html>
