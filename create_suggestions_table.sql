-- إنشاء جدول الاقتراحات
CREATE TABLE IF NOT EXISTS `suggestions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `suggestion_text` TEXT NOT NULL,
    `status` ENUM('pending', 'reviewed', 'implemented', 'rejected') DEFAULT 'pending',
    `admin_notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة بعض البيانات التجريبية (اختياري)
INSERT INTO `suggestions` (`customer_id`, `name`, `email`, `suggestion_text`, `status`) VALUES
(NULL, 'محمد أحمد', 'mohamed@example.com', 'أقترح إضافة خاصية البحث عن المنتجات حسب اللون', 'pending'),
(NULL, 'سارة محمد', 'sara@example.com', 'أتمنى لو كان هناك خيار للدفع عند الاستلام', 'pending'),
(NULL, 'أحمد علي', 'ahmed@example.com', 'أقترح إضافة تقييمات للمتاجر وليس فقط للمنتجات', 'pending');
