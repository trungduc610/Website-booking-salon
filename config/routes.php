<?php
// ============================================================
//  config/routes.php
//  Bảng định tuyến: URL → [Controller, method]
// ============================================================

return [
    // ---------- PUBLIC (không cần đăng nhập) ----------
    'GET'  => [
        '/'                         => ['PublicController',   'home'],
        '/search'                   => ['PublicController',   'search'],
        // FIX: 3 route bị thiếu hoàn toàn — layout có link footer trỏ tới
        // đây trên MỌI TRANG nhưng không route nào khớp → 404 khi bấm
        // "Trung tâm trợ giúp" / "Liên hệ" / "Chính sách bảo mật".
        '/help'                     => ['PublicController',   'help'],
        '/contact'                  => ['PublicController',   'contact'],
        '/privacy'                  => ['PublicController',   'privacy'],
        '/salon/{slug}'             => ['PublicController',   'salonDetail'],
        '/book'                     => ['PublicController',   'bookingForm'],
        '/book/success'             => ['PublicController',   'bookingSuccess'],
        '/login'                    => ['AuthController',     'loginForm'],
        '/register'                 => ['AuthController',     'registerForm'],
        '/logout'                   => ['AuthController',     'logout'],

        // ---------- CUSTOMER ----------
        '/customer/dashboard'       => ['CustomerController', 'dashboard'],
        '/customer/bookings'        => ['CustomerController', 'myBookings'],
        '/customer/bookings/{id}'   => ['CustomerController', 'bookingDetail'],
        '/customer/profile'         => ['CustomerController', 'profile'],
        '/customer/vouchers'        => ['CustomerController', 'myVouchers'],

        // ---------- SALON ----------
        '/salon/dashboard'          => ['SalonController',    'dashboard'],
        '/salon/bookings'           => ['SalonController',    'bookings'],
        '/salon/services'           => ['SalonController',    'services'],
        '/salon/staff'              => ['SalonController',    'staff'],
        '/salon/promotions'         => ['SalonController',    'promotions'],
        '/salon/reviews'            => ['SalonController',    'reviews'],
        '/salon/attendance'         => ['SalonController',    'attendance'],
        '/salon/profile'            => ['SalonController',    'profile'],
        '/salon/onboarding'         => ['SalonController',    'onboarding'],

        // ---------- ADMIN ----------
        '/admin/dashboard'          => ['AdminController',    'dashboard'],
        '/admin/salons'             => ['AdminController',    'salons'],
        '/admin/users'              => ['AdminController',    'users'],
        '/admin/reviews'            => ['AdminController',    'reviews'],
        '/admin/settings'           => ['AdminController',    'settings'],

        // ---------- API (kiểm tra khung giờ trống — dùng cho JS form đặt lịch) ----------
        '/api/availability'         => ['AvailabilityController', 'check'],
        '/api/slots'                => ['AvailabilityController', 'slots'],
    ],

    'POST' => [
        '/login'                    => ['AuthController',     'login'],
        '/register'                 => ['AuthController',     'register'],
        '/book'                     => ['BookingController',  'create'],
        '/customer/bookings/{id}/cancel'  => ['BookingController', 'cancel'],
        '/salon/bookings/{id}/confirm'    => ['BookingController', 'confirm'],
        '/salon/bookings/{id}/reject'     => ['BookingController', 'reject'],
        '/salon/bookings/{id}/complete'   => ['BookingController', 'complete'],
        '/salon/bookings/{id}/checkin'    => ['BookingController', 'checkIn'],
        '/salon/bookings/{id}/start'      => ['BookingController', 'start'],
        '/salon/bookings/{id}/noshow'     => ['BookingController', 'noShow'],
        '/salon/services/create'    => ['ServiceController',  'create'],
        '/salon/services/{id}/edit' => ['ServiceController',  'update'],
        '/salon/services/{id}/delete'     => ['ServiceController', 'delete'],
        '/salon/staff/create'       => ['StaffController',    'create'],
        '/salon/profile/update'     => ['SalonController',    'updateProfile'],
        '/customer/profile/update'  => ['CustomerController', 'updateProfile'],
        '/reviews/create'           => ['ReviewController',   'create'],
        '/admin/salons/{id}/approve'      => ['AdminController',  'approveSalon'],
        '/admin/salons/{id}/reject'       => ['AdminController',  'rejectSalon'],
    ],
];
