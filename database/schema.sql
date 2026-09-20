-- ============================================================
--  database/schema.sql
--  GlowBook — Schema MySQL đầy đủ (ĐÃ GỘP SẴN BẢN VÁ KIỂM TOÁN)
--
--  Chạy lệnh:  mysql -u root -p < schema.sql
--  (Chỉ cần MỘT file này — không còn file migration riêng nào phải chạy
--  thêm. Các fix về charset tiếng Việt, chống trùng lịch, ràng buộc dữ
--  liệu... đã nằm thẳng trong các CREATE TABLE bên dưới, đánh dấu bằng
--  chú thích "-- FIX:" tại đúng chỗ sửa.)
--
--  An toàn để chạy lại nhiều lần: mọi CREATE TABLE đều có IF NOT EXISTS,
--  mọi INSERT dữ liệu mẫu đều dùng INSERT IGNORE — chạy lại không báo lỗi
--  và không tạo dữ liệu trùng.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `glowbook_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `glowbook_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
--  1. NGƯỜI DÙNG & PHÂN QUYỀN
-- ============================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`       VARCHAR(50)  NOT NULL UNIQUE,   -- VD: PLATFORM_ADMIN, CUSTOMER
  `name`       VARCHAR(100) NOT NULL,
  `level`      ENUM('PLATFORM','TENANT','BRANCH','CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`        VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255),
  `resource`    VARCHAR(100) NOT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `scope`       ENUM('PLATFORM','TENANT','BRANCH','SELF','PUBLIC') NOT NULL DEFAULT 'SELF',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_resource_action` (`resource`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email`             VARCHAR(191) NOT NULL UNIQUE,
  `phone`             VARCHAR(20)  UNIQUE,
  `password_hash`     VARCHAR(255) NOT NULL,
  `full_name`         VARCHAR(150) NOT NULL,
  `address`           VARCHAR(300),
  `avatar`            VARCHAR(300),           -- đường dẫn ảnh đại diện
  `gender`            ENUM('MALE','FEMALE','OTHER'),
  `date_of_birth`     DATE,
  `is_email_verified` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_phone_verified` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at`     DATETIME,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME,
  INDEX `idx_email`  (`email`),
  INDEX `idx_phone`  (`phone`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `role_id`     INT UNSIGNED NOT NULL,
  `business_id` INT UNSIGNED,    -- null = platform role
  `branch_id`   INT UNSIGNED,    -- null = áp dụng cho toàn business
  `granted_by`  INT UNSIGNED,
  `granted_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME,
  UNIQUE KEY `uq_user_role_scope` (`user_id`, `role_id`, `business_id`, `branch_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `account_tokens` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       ENUM('EMAIL_VERIFICATION','PASSWORD_RESET') NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_type` (`user_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  2. ĐỊA LÝ
-- ============================================================

CREATE TABLE IF NOT EXISTS `provinces` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL UNIQUE,
  `code`       VARCHAR(10)  UNIQUE,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `districts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `province_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `code`        VARCHAR(10),
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`province_id`) REFERENCES `provinces`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_province_district` (`province_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  3. DOANH NGHIỆP / SALON
-- ============================================================

CREATE TABLE IF NOT EXISTS `business_owner_profiles` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`             INT UNSIGNED NOT NULL UNIQUE,
  `company_name`        VARCHAR(200),
  `tax_code`            VARCHAR(50) UNIQUE,
  `identity_card_number` VARCHAR(50),
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          DATETIME,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `businesses` (
  `id`                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id`                  INT UNSIGNED NOT NULL,
  `name`                      VARCHAR(200) NOT NULL,
  `slug`                      VARCHAR(200) NOT NULL UNIQUE,
  `description`               TEXT,
  `contact_email`             VARCHAR(191),
  `contact_phone`             VARCHAR(20),
  `address_line`              VARCHAR(300),
  `logo`                      VARCHAR(300),
  `status`                    ENUM('DRAFT','PENDING','PENDING_REVIEW','NEED_MORE_INFO','APPROVED','ACTIVE','SUSPENDED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `review_note`               TEXT,
  `submitted_at`              DATETIME,
  `reviewed_at`               DATETIME,
  `onboarding_step`           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `booking_restricted_at`     DATETIME,
  `booking_restriction_reason` VARCHAR(500),
  `trust_score`               DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `created_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`                DATETIME,
  FOREIGN KEY (`owner_id`) REFERENCES `business_owner_profiles`(`id`),
  -- FIX: mở rộng idx_status(status) thành (status, deleted_at) vì mọi
  -- truy vấn danh sách salon đều lọc "status = 'ACTIVE' AND deleted_at IS NULL" cùng lúc.
  INDEX `idx_status_deleted` (`status`, `deleted_at`),
  INDEX `idx_owner`      (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branches` (
  `id`                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id`              INT UNSIGNED NOT NULL,
  `name`                     VARCHAR(200) NOT NULL,
  `public_name`              VARCHAR(200),
  `description`              TEXT,
  `address_line`             VARCHAR(300),
  `district_id`              INT UNSIGNED,
  `ward`                     VARCHAR(100),
  `latitude`                 DECIMAL(9,6),
  `longitude`                DECIMAL(9,6),
  `phone`                    VARCHAR(20),
  `email`                    VARCHAR(191),
  `timezone`                 VARCHAR(50) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  `service_mode`             ENUM('AT_LOCATION','MOBILE','BOTH') NOT NULL DEFAULT 'AT_LOCATION',
  `booking_confirmation_mode` ENUM('MANUAL_CONFIRMATION','AUTO_CONFIRMATION') NOT NULL DEFAULT 'MANUAL_CONFIRMATION',
  `staff_assignment_mode`    ENUM('CUSTOMER_SELECTS_STAFF','AUTO_ASSIGN_IF_ANY_STAFF','MANUAL_ASSIGN_BY_RECEPTIONIST') NOT NULL DEFAULT 'AUTO_ASSIGN_IF_ANY_STAFF',
  `pending_hold_minutes`     SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `status`                   ENUM('PENDING','ACTIVE','INACTIVE') NOT NULL DEFAULT 'PENDING',
  `review_status`            ENUM('DRAFT','SUBMITTED','PENDING_REVIEW','NEED_MORE_INFO','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `operational_status`       ENUM('INACTIVE','READY_TO_PUBLISH','ACTIVE','PAUSED','SUSPENDED','CLOSED','ARCHIVED') NOT NULL DEFAULT 'INACTIVE',
  `published_at`             DATETIME,
  `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`               DATETIME,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`),
  FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE SET NULL,
  INDEX `idx_business`    (`business_id`),
  INDEX `idx_status`      (`status`),
  INDEX `idx_op_status`   (`operational_status`),
  INDEX `idx_geo`         (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branch_working_hours` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`  INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '0=CN, 1=T2, ..., 6=T7',
  `open_time`  TIME NOT NULL,
  `close_time` TIME NOT NULL,
  `is_closed`  TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_branch_day` (`branch_id`, `day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branch_holidays` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`  INT UNSIGNED NOT NULL,
  `date`       DATE NOT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `is_closed`  TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_branch_date` (`branch_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branch_booking_policies` (
  `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`             INT UNSIGNED NOT NULL UNIQUE,
  `lead_time_minutes`     SMALLINT UNSIGNED NOT NULL DEFAULT 0     COMMENT 'Đặt trước tối thiểu X phút',
  `booking_horizon_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 90    COMMENT 'Đặt trước tối đa X ngày',
  `cancellation_hours`    SMALLINT UNSIGNED NOT NULL DEFAULT 24    COMMENT 'Hủy miễn phí trước X giờ',
  `reschedule_hours`      SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  `late_cancel_fee_pct`   TINYINT UNSIGNED  NOT NULL DEFAULT 50    COMMENT '% phí khi hủy trễ',
  `no_show_fee_pct`       TINYINT UNSIGNED  NOT NULL DEFAULT 100   COMMENT '% phí khi không đến',
  `allow_walk_in`         TINYINT(1)        NOT NULL DEFAULT 1,
  `allow_counter_booking` TINYINT(1)        NOT NULL DEFAULT 1,
  `default_buffer_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salon_members` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `business_id` INT UNSIGNED NOT NULL,
  `branch_id`   INT UNSIGNED,
  `role`        VARCHAR(50)  NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  DATETIME,
  UNIQUE KEY `uq_user_business` (`user_id`, `business_id`),
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  4. NHÂN VIÊN
-- ============================================================

CREATE TABLE IF NOT EXISTS `staff_profiles` (
  `id`                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`                INT UNSIGNED UNIQUE,         -- null nếu chưa có tài khoản
  `branch_id`              INT UNSIGNED NOT NULL,
  `full_name`              VARCHAR(150) NOT NULL,
  `position`               VARCHAR(100),
  `bio`                    TEXT,
  `employee_code`          VARCHAR(50),
  `experience_years`       TINYINT UNSIGNED,
  `public_visible`         TINYINT(1)   NOT NULL DEFAULT 1,
  `is_bookable`            TINYINT(1)   NOT NULL DEFAULT 0,
  `status`                 ENUM('PROFILE_ONLY','INVITED','ACTIVE','LOCKED','INACTIVE','ON_LEAVE') NOT NULL DEFAULT 'ACTIVE',
  `hired_at`               DATE,
  `emergency_contact_name` VARCHAR(150),
  `emergency_contact_phone` VARCHAR(20),
  `avatar`                 VARCHAR(300),
  `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`             DATETIME,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_branch_code` (`branch_id`, `employee_code`),
  INDEX `idx_branch_status` (`branch_id`, `status`, `is_bookable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `staff_working_hours` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `staff_id`    INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT UNSIGNED NOT NULL,
  `start_time`  TIME NOT NULL,
  `end_time`    TIME NOT NULL,
  `is_off`      TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (`staff_id`) REFERENCES `staff_profiles`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_staff_day` (`staff_id`, `day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `staff_leaves` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `staff_id`    INT UNSIGNED NOT NULL,
  `start_at`    DATETIME NOT NULL,
  `end_at`      DATETIME NOT NULL,
  `reason`      VARCHAR(500),
  `status`      ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `reviewed_by` INT UNSIGNED,
  `review_note` VARCHAR(500),
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`staff_id`)    REFERENCES `staff_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_staff_dates` (`staff_id`, `start_at`, `end_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  5. DỊCH VỤ & COMBO
-- ============================================================

CREATE TABLE IF NOT EXISTS `service_categories` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `parent_id`   INT UNSIGNED,               -- danh mục cha (null = cấp gốc)
  `name`        VARCHAR(200) NOT NULL,
  `slug`        VARCHAR(200) NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  DATETIME,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`)   REFERENCES `service_categories`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `uq_biz_slug` (`business_id`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `services` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`           INT UNSIGNED NOT NULL,
  `business_id`         INT UNSIGNED NOT NULL,
  `category_id`         INT UNSIGNED NOT NULL,
  `name`                VARCHAR(200) NOT NULL,
  `description`         TEXT,
  `price`               DECIMAL(12,2) NOT NULL DEFAULT 0,
  `duration_minutes`    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `bookable`            TINYINT(1)    NOT NULL DEFAULT 1,
  `status`              ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `cover_image`         VARCHAR(300),
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          DATETIME,
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `service_categories`(`id`),
  -- FIX: mở rộng thành 4 cột — ServiceModel::findForBranch() và trang đặt
  -- lịch luôn lọc đủ cả 4 điều kiện này cùng lúc.
  INDEX `idx_branch_bookable` (`branch_id`, `bookable`, `status`, `deleted_at`),
  INDEX `idx_price`         (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `staff_services` (
  `staff_id`   INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`staff_id`, `service_id`),
  FOREIGN KEY (`staff_id`)   REFERENCES `staff_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combos` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id`  INT UNSIGNED NOT NULL,
  `branch_id`    INT UNSIGNED NOT NULL,
  `name`         VARCHAR(200) NOT NULL,
  `description`  TEXT,
  `combo_price`  DECIMAL(12,2) NOT NULL,
  `valid_from`   DATETIME,
  `valid_to`     DATETIME,
  `max_usage`    INT UNSIGNED,
  `used_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `status`       ENUM('ACTIVE','INACTIVE','PAUSED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  `cover_image`  VARCHAR(300),
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`   DATETIME,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`) ON DELETE CASCADE,
  INDEX `idx_branch_status` (`branch_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combo_services` (
  `combo_id`          INT UNSIGNED NOT NULL,
  `service_id`        INT UNSIGNED NOT NULL,
  `quantity`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `sort_order`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `price_snapshot`    DECIMAL(12,2) NOT NULL,
  `duration_snapshot` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`combo_id`, `service_id`),
  FOREIGN KEY (`combo_id`)   REFERENCES `combos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  6. KHÁCH HÀNG
-- ============================================================

CREATE TABLE IF NOT EXISTS `customer_profiles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL UNIQUE,
  `note`        TEXT,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  DATETIME,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  7. ĐẶT LỊCH
-- ============================================================

CREATE TABLE IF NOT EXISTS `bookings` (
  `id`                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id`            INT UNSIGNED,     -- null nếu là guest
  `branch_id`              INT UNSIGNED NOT NULL,
  `booking_code`           VARCHAR(30) NOT NULL UNIQUE,
  `appointment_date`       DATE NOT NULL,
  `appointment_start_time` TIME NOT NULL,
  `appointment_end_time`   TIME NOT NULL,
  `status`                 ENUM('PENDING','CONFIRMED','CHECKED_IN','IN_PROGRESS','COMPLETED','CANCELLED','NO_SHOW','REJECTED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  `source`                 ENUM('ONLINE_WEB','ONLINE_APP','WALK_IN','PHONE','STAFF_CREATED','ADMIN_CREATED') NOT NULL DEFAULT 'ONLINE_WEB',
  `total_amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `voucher_id`             INT UNSIGNED,
  `voucher_discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  -- FIX: bản gốc để `final_amount` cho phép NULL, khiến SUM(final_amount)
  -- ở dashboard doanh thu ra sai (NULL bị SUM() bỏ qua âm thầm).
  `final_amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `note`                   TEXT,
  `cancel_reason`          TEXT,
  `cancelled_at`           DATETIME,
  `cancelled_by`           INT UNSIGNED,
  `pending_expires_at`     DATETIME,
  `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`             DATETIME,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_profiles`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`),
  FOREIGN KEY (`cancelled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_customer`     (`customer_id`),
  INDEX `idx_branch_date`  (`branch_id`, `appointment_date`),
  INDEX `idx_status`       (`status`),
  INDEX `idx_pending_exp`  (`status`, `pending_expires_at`),
  -- FIX (chống double-booking): index này phủ đúng câu WHERE mà
  -- BookingModel::findConflict() dùng để kiểm tra trùng lịch. Câu đó chạy
  -- trong lúc đang giữ khoá transaction (SELECT ... FOR UPDATE), nên BẮT
  -- BUỘC phải có index — nếu không mọi lượt đặt lịch sẽ quét toàn bảng
  -- trong lúc giữ khoá, biến việc chống trùng lịch thành nút thắt cổ chai.
  INDEX `idx_conflict_lookup` (`branch_id`, `appointment_date`, `status`,
                                `appointment_start_time`, `appointment_end_time`),
  -- FIX (toàn vẹn dữ liệu): chặn ngay ở tầng DB việc giờ kết thúc sớm hơn
  -- hoặc bằng giờ bắt đầu — trước đây chỉ tin vào code PHP tính toán đúng.
  CONSTRAINT `chk_time_order` CHECK (`appointment_end_time` > `appointment_start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `booking_contacts` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL UNIQUE,
  `full_name`  VARCHAR(150) NOT NULL,
  `phone`      VARCHAR(20),
  `email`      VARCHAR(191),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `booking_services` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id`           INT UNSIGNED NOT NULL,
  `service_id`           INT UNSIGNED NOT NULL,
  `combo_id`             INT UNSIGNED,
  `staff_id`             INT UNSIGNED,
  `service_name_snapshot` VARCHAR(200) NOT NULL,   -- tên dịch vụ tại thời điểm đặt
  `price_at_booking`     DECIMAL(12,2) NOT NULL,
  `duration_minutes`     SMALLINT UNSIGNED NOT NULL,
  `sort_order`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status`               ENUM('SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED','SKIPPED') NOT NULL DEFAULT 'SCHEDULED',
  `item_start_at`        DATETIME,
  `item_end_at`          DATETIME,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`),
  FOREIGN KEY (`combo_id`)   REFERENCES `combos`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`staff_id`)   REFERENCES `staff_profiles`(`id`) ON DELETE SET NULL,
  INDEX `idx_booking` (`booking_id`),
  -- FIX: mở rộng từ idx_staff(staff_id) thành (staff_id, status) — câu
  -- findConflict() lọc thêm theo status NOT IN ('CANCELLED','SKIPPED'),
  -- index 2 cột giúp câu đó chạy nhanh thay vì lọc thêm sau khi quét.
  INDEX `idx_staff_status` (`staff_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `booking_status_histories` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id`  INT UNSIGNED NOT NULL,
  `status`      VARCHAR(30)  NOT NULL,
  `changed_by`  INT UNSIGNED,
  `note`        VARCHAR(500),
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointment_change_requests` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id`          INT UNSIGNED NOT NULL,
  `requested_by`        INT UNSIGNED NOT NULL,
  `request_type`        ENUM('RESCHEDULE','CHANGE_STAFF','CANCEL') NOT NULL,
  `proposed_date`       DATE,
  `proposed_start_time` TIME,
  `proposed_end_time`   TIME,
  `proposed_staff_id`   INT UNSIGNED,
  `reason`              VARCHAR(500),
  `status`              ENUM('PENDING','APPROVED','REJECTED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  `reviewed_by`         INT UNSIGNED,
  `reviewed_at`         DATETIME,
  `review_note`         VARCHAR(500),
  `expires_at`          DATETIME NOT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`)        REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`)      REFERENCES `users`(`id`),
  FOREIGN KEY (`reviewed_by`)       REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`proposed_staff_id`) REFERENCES `staff_profiles`(`id`) ON DELETE SET NULL,
  INDEX `idx_booking_status` (`booking_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  8. THANH TOÁN
-- ============================================================

CREATE TABLE IF NOT EXISTS `payments` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id`      INT UNSIGNED NOT NULL,
  `amount`          DECIMAL(12,2) NOT NULL,
  `method`          ENUM('CASH','BANK_TRANSFER','MOMO','VNPAY','ZALOPAY','CREDIT_CARD') NOT NULL,
  `status`          ENUM('PENDING','PAID','PARTIALLY_PAID','FAILED','REFUNDED','PARTIALLY_REFUNDED') NOT NULL DEFAULT 'PENDING',
  `transaction_ref` VARCHAR(200),
  `paid_at`         DATETIME,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`),
  INDEX `idx_booking` (`booking_id`),
  INDEX `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `refund_requests` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_id`      INT UNSIGNED NOT NULL,
  `amount`          DECIMAL(12,2) NOT NULL,
  `reason`          TEXT NOT NULL,
  `status`          ENUM('PENDING','APPROVED','REJECTED','PROCESSING','REFUNDED','FAILED') NOT NULL DEFAULT 'PENDING',
  `requested_by`    INT UNSIGNED NOT NULL,
  `reviewed_by`     INT UNSIGNED,
  `review_note`     TEXT,
  `reviewed_at`     DATETIME,
  `processed_at`    DATETIME,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`payment_id`)   REFERENCES `payments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`reviewed_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_payment_status` (`payment_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  9. VOUCHER & KHUYẾN MÃI
-- ============================================================

CREATE TABLE IF NOT EXISTS `vouchers` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id`          INT UNSIGNED,     -- null = platform voucher
  `code`                 VARCHAR(50)  NOT NULL UNIQUE,
  `name`                 VARCHAR(200) NOT NULL,
  `description`          TEXT,
  `discount_type`        ENUM('PERCENTAGE','FIXED_AMOUNT') NOT NULL,
  `discount_value`       DECIMAL(12,2) NOT NULL,
  `min_order_value`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `max_discount`         DECIMAL(12,2),
  `total_quantity`       INT UNSIGNED NOT NULL DEFAULT 0   COMMENT '0 = không giới hạn',
  `used_quantity`        INT UNSIGNED NOT NULL DEFAULT 0,
  `max_usage_per_customer` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `start_date`           DATETIME NOT NULL,
  `end_date`             DATETIME NOT NULL,
  `status`               ENUM('ACTIVE','INACTIVE','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  `created_by_platform`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`           DATETIME,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
  INDEX `idx_status`     (`status`),
  INDEX `idx_dates`      (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_vouchers` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `voucher_id`     INT UNSIGNED NOT NULL,
  `customer_id`    INT UNSIGNED NOT NULL,
  `status`         ENUM('ACTIVE','USED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  `acquired_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at`        DATETIME,
  `used_booking_id` INT UNSIGNED UNIQUE,
  `expires_at`     DATETIME,
  FOREIGN KEY (`voucher_id`)      REFERENCES `vouchers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`)     REFERENCES `customer_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`used_booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `uq_voucher_customer` (`voucher_id`, `customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotions` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id`        INT UNSIGNED,
  `name`               VARCHAR(200) NOT NULL,
  `description`        TEXT,
  `discount_type`      ENUM('PERCENTAGE','FIXED_AMOUNT') NOT NULL,
  `discount_value`     DECIMAL(12,2) NOT NULL,
  `start_date`         DATETIME NOT NULL,
  `end_date`           DATETIME NOT NULL,
  `status`             ENUM('ACTIVE','INACTIVE','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  `created_by_platform` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`         DATETIME,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  10. ĐÁNH GIÁ
-- ============================================================

CREATE TABLE IF NOT EXISTS `reviews` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id`     INT UNSIGNED NOT NULL UNIQUE,
  `customer_id`    INT UNSIGNED NOT NULL,
  `branch_id`      INT UNSIGNED NOT NULL,
  `overall_rating` TINYINT UNSIGNED NOT NULL COMMENT '1-5 sao',
  `comment`        TEXT,
  `is_anonymous`   TINYINT(1)    NOT NULL DEFAULT 0,
  `status`         ENUM('PENDING','PUBLISHED','HIDDEN','REJECTED') NOT NULL DEFAULT 'PENDING',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME,
  FOREIGN KEY (`booking_id`)  REFERENCES `bookings`(`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customer_profiles`(`id`),
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`),
  INDEX `idx_branch_status` (`branch_id`, `status`),
  INDEX `idx_customer`      (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `review_service_ratings` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `review_id`        INT UNSIGNED NOT NULL,
  `booking_service_id` INT UNSIGNED NOT NULL UNIQUE,
  `staff_id`         INT UNSIGNED,
  `rating`           TINYINT UNSIGNED NOT NULL,
  `comment`          TEXT,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`review_id`)          REFERENCES `reviews`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_service_id`) REFERENCES `booking_services`(`id`),
  FOREIGN KEY (`staff_id`)           REFERENCES `staff_profiles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_comments` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id`     INT UNSIGNED NOT NULL,
  `review_id`       INT UNSIGNED UNIQUE,    -- null nếu là comment độc lập
  `customer_id`     INT UNSIGNED NOT NULL,  -- ở đây có thể là user_id của salon trả lời
  `parent_id`       INT UNSIGNED,
  `content`         TEXT NOT NULL,
  `status`          ENUM('VISIBLE','HIDDEN') NOT NULL DEFAULT 'VISIBLE',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`),
  FOREIGN KEY (`review_id`)   REFERENCES `reviews`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customer_profiles`(`id`),
  FOREIGN KEY (`parent_id`)   REFERENCES `business_comments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  11. CHẤM CÔNG NHÂN VIÊN
-- ============================================================

CREATE TABLE IF NOT EXISTS `staff_attendances` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id`          INT UNSIGNED NOT NULL,
  `branch_id`            INT UNSIGNED NOT NULL,
  `staff_id`             INT UNSIGNED NOT NULL,
  `work_date`            DATE NOT NULL,
  `scheduled_start_time` TIME NOT NULL,
  `scheduled_end_time`   TIME NOT NULL,
  `check_in_at`          DATETIME,
  `check_out_at`         DATETIME,
  `check_in_method`      ENUM('QR','MANUAL','ADMIN'),
  `check_out_method`     ENUM('QR','MANUAL','ADMIN'),
  `status`               ENUM('NOT_CHECKED_IN','CHECKED_IN','CHECKED_OUT','ABSENT','ON_LEAVE') NOT NULL DEFAULT 'NOT_CHECKED_IN',
  `late_minutes`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `early_leave_minutes`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `overtime_minutes`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `note`                 VARCHAR(500),
  `adjusted_by`          INT UNSIGNED,
  `adjusted_at`          DATETIME,
  `adjustment_reason`    VARCHAR(500),
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`staff_id`)    REFERENCES `staff_profiles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`adjusted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `uq_staff_branch_date` (`staff_id`, `branch_id`, `work_date`),
  INDEX `idx_branch_date` (`branch_id`, `work_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  12. THÔNG BÁO
-- ============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`          INT UNSIGNED NOT NULL,
  `type`             VARCHAR(100) NOT NULL,   -- VD: BOOKING_CONFIRMED, NEW_REVIEW
  `severity`         ENUM('INFO','WARNING','ERROR','SUCCESS') NOT NULL DEFAULT 'INFO',
  `title`            VARCHAR(300) NOT NULL,
  `body`             TEXT,
  `is_read`          TINYINT(1)   NOT NULL DEFAULT 0,
  `read_at`          DATETIME,
  `action_url`       VARCHAR(500),
  `related_booking_id` INT UNSIGNED,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)            REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  13. MEDIA FILES
-- ============================================================

CREATE TABLE IF NOT EXISTS `media_files` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `url`           VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(300),
  `safe_name`     VARCHAR(300),
  `mime_type`     VARCHAR(100),
  `file_size`     INT UNSIGNED,
  `uploaded_by`   INT UNSIGNED,
  `business_id`   INT UNSIGNED,
  `branch_id`     INT UNSIGNED,
  `entity_type`   VARCHAR(100),    -- VD: 'salon', 'service', 'staff'
  `entity_id`     INT UNSIGNED,
  `visibility`    ENUM('PUBLIC','PRIVATE') NOT NULL DEFAULT 'PUBLIC',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  14. AUDIT LOG
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED,
  `action`      VARCHAR(100) NOT NULL,   -- VD: CREATE, UPDATE, DELETE, LOGIN
  `entity_type` VARCHAR(100) NOT NULL,
  `entity_id`   INT UNSIGNED,
  `old_data`    JSON,
  `new_data`    JSON,
  `ip_address`  VARCHAR(45),
  `user_agent`  VARCHAR(500),
  `reason`      VARCHAR(500),
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_entity`    (`entity_type`, `entity_id`),
  INDEX `idx_user_time` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  15. CÀI ĐẶT PLATFORM
-- ============================================================

CREATE TABLE IF NOT EXISTS `platform_settings` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`        VARCHAR(100) NOT NULL UNIQUE,
  `value`      JSON NOT NULL,
  `updated_by` INT UNSIGNED,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  DỮ LIỆU MẪU (SEED)
-- ============================================================

-- Roles
INSERT IGNORE INTO `roles` (`code`, `name`, `level`) VALUES
  ('PLATFORM_ADMIN',  'Quản trị Platform',  'PLATFORM'),
  ('COMPLIANCE',      'Tuân thủ pháp lý',   'PLATFORM'),
  ('SUPPORT',         'Hỗ trợ khách hàng',  'PLATFORM'),
  ('MARKETING',       'Marketing',          'PLATFORM'),
  ('FINANCE',         'Tài chính',          'PLATFORM'),
  ('BUSINESS_OWNER',  'Chủ salon',          'TENANT'),
  ('BRANCH_MANAGER',  'Quản lý chi nhánh',  'BRANCH'),
  ('RECEPTIONIST',    'Lễ tân',             'BRANCH'),
  ('STAFF',           'Nhân viên',          'BRANCH'),
  ('CUSTOMER',        'Khách hàng',         'CUSTOMER'),
  ('GUEST',           'Khách vãng lai',     'CUSTOMER');

-- Tỉnh/thành mẫu
INSERT IGNORE INTO `provinces` (`name`, `code`) VALUES
  ('Hà Nội', 'HN'), ('TP. Hồ Chí Minh', 'HCM'), ('Đà Nẵng', 'DN'),
  ('Cần Thơ', 'CT'), ('Hải Phòng', 'HP'), ('Bình Dương', 'BD');

-- Quận mẫu cho HCM
INSERT IGNORE INTO `districts` (`province_id`, `name`) VALUES
  (2,'Quận 1'), (2,'Quận 2'), (2,'Quận 3'), (2,'Quận 7'),
  (2,'Bình Thạnh'), (2,'Tân Bình'), (2,'Gò Vấp'), (2,'Phú Nhuận');

-- Quận mẫu cho Hà Nội
INSERT IGNORE INTO `districts` (`province_id`, `name`) VALUES
  (1,'Hoàn Kiếm'), (1,'Ba Đình'), (1,'Đống Đa'), (1,'Hai Bà Trưng'),
  (1,'Cầu Giấy'), (1,'Thanh Xuân'), (1,'Hoàng Mai');

-- Tài khoản Admin mặc định (password: Password123!)
-- Hash được tạo bằng password_hash('Password123!', PASSWORD_BCRYPT, ['cost'=>12])
INSERT IGNORE INTO `users` (`email`, `password_hash`, `full_name`, `is_email_verified`, `is_active`)
VALUES ('admin@glowbook.vn',
        '$2y$12$eI0urHX2Y7HzXV2jdNKqYOaFgWmNJ8nqrjg6JYmUMlm5c0JrRD4yK',
        'Platform Admin', 1, 1);

-- Gán role PLATFORM_ADMIN cho user vừa tạo
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.id, r.id FROM `users` u, `roles` r
WHERE u.email = 'admin@glowbook.vn' AND r.code = 'PLATFORM_ADMIN';

-- Cài đặt platform mặc định
INSERT IGNORE INTO `platform_settings` (`key`, `value`) VALUES
  ('platform_fee_rate',    '{"rate": 0.05, "description": "Phí platform 5% mỗi booking"}'),
  ('booking_time_zone',    '"Asia/Ho_Chi_Minh"'),
  ('max_booking_advance_days', '90'),
  ('support_email',        '"support@glowbook.vn"');

-- ============================================================
--  DỮ LIỆU DEMO: TÀI KHOẢN, SALON, DỊCH VỤ, NHÂN VIÊN
-- ============================================================

-- 1. Tài khoản Chủ Salon (owner@glowbook.vn / Password123!)
INSERT IGNORE INTO `users` (`id`, `email`, `password_hash`, `full_name`, `phone`, `is_email_verified`, `is_active`)
VALUES (2, 'owner@glowbook.vn', '$2y$12$eI0urHX2Y7HzXV2jdNKqYOaFgWmNJ8nqrjg6JYmUMlm5c0JrRD4yK', 'Nguyễn Thị Hương (Chủ Salon)', '0909123456', 1, 1);

INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT 2, id FROM `roles` WHERE `code` = 'BUSINESS_OWNER';

INSERT IGNORE INTO `business_owner_profiles` (`id`, `user_id`, `company_name`, `tax_code`)
VALUES (1, 2, 'Công Ty TNHH Làm Đẹp Bella', '0315894123');

-- 2. Tài khoản Khách hàng (customer@glowbook.vn / Password123!)
INSERT IGNORE INTO `users` (`id`, `email`, `password_hash`, `full_name`, `phone`, `is_email_verified`, `is_active`)
VALUES (3, 'customer@glowbook.vn', '$2y$12$eI0urHX2Y7HzXV2jdNKqYOaFgWmNJ8nqrjg6JYmUMlm5c0JrRD4yK', 'Trần Mai Phương', '0912888999', 1, 1);

INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT 3, id FROM `roles` WHERE `code` = 'CUSTOMER';

INSERT IGNORE INTO `customer_profiles` (`id`, `user_id`, `note`)
VALUES (1, 3, 'Khách hàng thân thiết VIP');

-- 3. Doanh nghiệp / Salon mẫu
INSERT IGNORE INTO `businesses` (`id`, `owner_id`, `name`, `slug`, `description`, `contact_email`, `contact_phone`, `address_line`, `status`, `trust_score`)
VALUES
(1, 1, 'Bella Hair & Spa Center', 'bella-hair-spa',
 'Hệ thống chăm sóc tóc và spa thư giãn chuẩn quốc tế hàng đầu tại TP.HCM. Không gian sang trọng, sản phẩm organic an toàn cho sức khỏe.',
 'contact@bellaspa.vn', '0909123456', '128 Hai Bà Trưng, Quận 1, TP.HCM', 'ACTIVE', 98.50),
(2, 1, 'Tokyo Nail & Lash Studio', 'tokyo-nail-lash',
 'Chuyên nghệ thuật vẽ móng phong cách Nhật Bản và nối mi lụa tự nhiên cao cấp. Đội ngũ nghệ nhân trên 5 năm kinh nghiệm.',
 'info@tokyonail.vn', '0909654321', '45 Cầu Giấy, Hà Nội', 'ACTIVE', 99.00);

-- 4. Chi nhánh
INSERT IGNORE INTO `branches` (`id`, `business_id`, `name`, `public_name`, `description`, `address_line`, `district_id`, `phone`, `status`, `operational_status`)
VALUES
(1, 1, 'Chi nhánh Bến Nghé - Q1', 'Bella Spa Quận 1', 'Cơ sở chính trung tâm Q1, có phòng VIP', '128 Hai Bà Trưng, Phường Bến Nghé, Quận 1', 1, '02838221199', 'ACTIVE', 'ACTIVE'),
(2, 2, 'Chi nhánh Cầu Giấy', 'Tokyo Nail Cầu Giấy', 'Không gian hiện đại, đậu xe ô tô thuận tiện', '45 Cầu Giấy, Quan Hoa, Cầu Giấy, Hà Nội', 13, '02437668899', 'ACTIVE', 'ACTIVE');

-- 5. Giờ làm việc chi nhánh (Thứ 2 - CN: 08:30 - 20:30)
INSERT IGNORE INTO `branch_working_hours` (`branch_id`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
(1, 0, '08:30:00', '20:30:00', 0),
(1, 1, '08:30:00', '20:30:00', 0),
(1, 2, '08:30:00', '20:30:00', 0),
(1, 3, '08:30:00', '20:30:00', 0),
(1, 4, '08:30:00', '20:30:00', 0),
(1, 5, '08:30:00', '20:30:00', 0),
(1, 6, '08:30:00', '20:30:00', 0),
(2, 0, '09:00:00', '21:00:00', 0),
(2, 1, '09:00:00', '21:00:00', 0),
(2, 2, '09:00:00', '21:00:00', 0),
(2, 3, '09:00:00', '21:00:00', 0),
(2, 4, '09:00:00', '21:00:00', 0),
(2, 5, '09:00:00', '21:00:00', 0),
(2, 6, '09:00:00', '21:00:00', 0);

-- 5b. Chính sách đặt lịch cho 2 chi nhánh mẫu (thêm mới — bản gốc bỏ sót,
-- khiến BookingModel::policyFor() luôn phải dùng giá trị mặc định dự phòng
-- trong code PHP thay vì cấu hình tường minh theo từng chi nhánh)
INSERT IGNORE INTO `branch_booking_policies`
  (`branch_id`, `lead_time_minutes`, `booking_horizon_days`, `cancellation_hours`, `default_buffer_minutes`)
VALUES
(1, 60, 90, 24, 10),
(2, 60, 90, 24, 10);

-- 6. Danh mục dịch vụ
INSERT IGNORE INTO `service_categories` (`id`, `business_id`, `name`, `slug`) VALUES
(1, 1, 'Dịch vụ tóc nữ', 'toc-nu'),
(2, 1, 'Chăm sóc da mặt (Facial Spa)', 'facial-spa'),
(3, 1, 'Massage thư giãn', 'massage-body'),
(4, 2, 'Nail nghệ thuật', 'nail-art'),
(5, 2, 'Nối mi thiết kế', 'noi-mi');

-- 7. Dịch vụ mẫu
INSERT IGNORE INTO `services` (`id`, `branch_id`, `business_id`, `category_id`, `name`, `description`, `price`, `duration_minutes`, `bookable`, `status`) VALUES
(1, 1, 1, 1, 'Cắt & Tạo kiểu tóc chuẩn Hàn Quốc', 'Tư vấn dáng tóc phù hợp khuôn mặt, gội đầu thư giãn thảo mộc và sấy tạo kiểu', 180000, 45, 1, 'ACTIVE'),
(2, 1, 1, 1, 'Nhuộm tóc thời trang Organic Collagen', 'Sử dụng thuốc nhuộm thảo mộc an toàn, bổ sung collagen bóng khỏe không xơ rối', 850000, 120, 1, 'ACTIVE'),
(3, 1, 1, 1, 'Uốn sóng lơi Layer bồng bềnh', 'Kỹ thuật uốn setting hiện đại giữ nếp tự nhiên từ 6-8 tháng', 950000, 150, 1, 'ACTIVE'),
(4, 1, 1, 2, 'Điện di vitamin C sáng mịn trẻ hóa', 'Làm sạch sâu, tẩy da chết enzym, xông hơi hút mụn và đi tinh chất C nguyên chất', 390000, 60, 1, 'ACTIVE'),
(5, 1, 1, 3, 'Massage Body đá nóng tinh dầu gừng', 'Giải tỏa căng thẳng, lưu thông khí huyết với đá núi lửa tự nhiên', 450000, 75, 1, 'ACTIVE'),
(6, 2, 2, 4, 'Sơn Gel cao cấp & Dưỡng viền móng OPI', 'Nhặt da sạch sẽ, tạo form móng theo yêu cầu, sơn 3 lớp gel bóng bền màu 4 tuần', 150000, 45, 1, 'ACTIVE'),
(7, 2, 2, 4, 'Vẽ móng Ombre đính đá phong cách Nhật', 'Thiết kế riêng từng ngón tay với đá swarovski và charm cao cấp', 350000, 75, 1, 'ACTIVE'),
(8, 2, 2, 5, 'Nối mi lụa One by One tự nhiên', 'Sợi mi mềm nhẹ như thật, không cộm ngứa, giữ độ cong hoàn hảo từ 3-5 tuần', 280000, 60, 1, 'ACTIVE');

-- 8. Nhân viên mẫu
INSERT IGNORE INTO `staff_profiles` (`id`, `branch_id`, `full_name`, `position`, `employee_code`, `experience_years`, `public_visible`, `is_bookable`, `status`) VALUES
(1, 1, 'Lê Hoàng Nam', 'Master Stylist tóc', 'ST-01', 7, 1, 1, 'ACTIVE'),
(2, 1, 'Vũ Thuỳ Linh', 'Kỹ thuật viên Spa & Facial', 'ST-02', 4, 1, 1, 'ACTIVE'),
(3, 2, 'Trần Bảo Ngọc', 'Nghệ nhân Nail Designer', 'ST-03', 5, 1, 1, 'ACTIVE'),
(4, 2, 'Nguyễn Kiều Oanh', 'Chuyên viên Nối mi', 'ST-04', 3, 1, 1, 'ACTIVE');

-- 9. Voucher khuyến mãi
INSERT IGNORE INTO `vouchers` (`id`, `business_id`, `code`, `name`, `discount_type`, `discount_value`, `min_order_value`, `start_date`, `end_date`, `status`, `total_quantity`) VALUES
(1, 1, 'BELLA10', 'Giảm 10% mừng khai trương', 'PERCENTAGE', 10.00, 150000, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 'ACTIVE', 100),
(2, 2, 'TOKYO50K', 'Giảm ngay 50.000đ cho đơn từ 200k', 'FIXED_AMOUNT', 50000.00, 200000, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 'ACTIVE', 50);

-- 10. Đánh giá mẫu
INSERT IGNORE INTO `bookings` (`id`, `customer_id`, `branch_id`, `booking_code`, `appointment_date`, `appointment_start_time`, `appointment_end_time`, `status`, `total_amount`, `final_amount`)
VALUES (1, 1, 1, 'GLW-DEMO-0001', CURDATE(), '09:00:00', '10:00:00', 'COMPLETED', 180000, 180000);

INSERT IGNORE INTO `reviews` (`id`, `booking_id`, `customer_id`, `branch_id`, `overall_rating`, `comment`, `is_anonymous`, `status`, `created_at`)
VALUES
(1, 1, 1, 1, 5, 'Dịch vụ tại Bella rất tuyệt vời! Bạn Nam cắt tóc siêu ưng ý, không gian thơm và sạch sẽ. Chắc chắn sẽ quay lại!', 0, 'PUBLISHED', NOW());

