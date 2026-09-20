<?php
// ============================================================
//  app/controllers/AuthController.php
// ============================================================
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/app/models/UserModel.php';

class AuthController extends Controller
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    // GET /login
    public function loginForm(): void
    {
        if (Session::isLoggedIn()) {
            $this->redirect($this->dashboardUrl());
        }
        $this->view('auth/login', ['pageTitle' => 'Đăng nhập']);
    }

    // POST /login
    public function login(): void
    {
        $this->verifyCsrf();

        $email    = $this->rawInput('email', '');
        $password = $this->rawInput('password', '');

        // Validation cơ bản
        if (empty($email) || empty($password)) {
            $this->flash('error', 'Vui lòng nhập đầy đủ email và mật khẩu.');
            $this->redirect('/login');
            return;
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !verifyPassword($password, $user['password_hash'])) {
            $this->flash('error', 'Email hoặc mật khẩu không đúng.');
            $this->redirect('/login');
            return;
        }

        if (!$user['is_active']) {
            $this->flash('error', 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ hỗ trợ.');
            $this->redirect('/login');
            return;
        }

        // Đăng nhập thành công
        Session::login($user);
        $this->users->updateLastLogin($user['id']);

        // Redirect về trang đã muốn vào trước đó (nếu có)
        $redirectTo = Session::get('redirect_after_login', '');
        Session::remove('redirect_after_login');

        $this->flash('success', 'Chào mừng bạn quay lại, ' . $user['full_name'] . '!');
        $this->redirect($redirectTo ?: $this->dashboardUrl($user['role_code'] ?? 'CUSTOMER'));
    }

    // GET /register
    public function registerForm(): void
    {
        if (Session::isLoggedIn()) {
            $this->redirect($this->dashboardUrl());
        }
        $this->view('auth/register', ['pageTitle' => 'Đăng ký tài khoản']);
    }

    // POST /register
    public function register(): void
    {
        $this->verifyCsrf();

        $fullName        = $this->input('full_name');
        $email           = $this->rawInput('email');
        $phone           = $this->input('phone');
        $password        = $this->rawInput('password');
        $passwordConfirm = $this->rawInput('password_confirm');

        // Validation
        $errors = [];
        if (empty($fullName))                   $errors[] = 'Họ tên không được để trống.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
        if (strlen($password) < 8)              $errors[] = 'Mật khẩu phải tối thiểu 8 ký tự.';
        if ($password !== $passwordConfirm)     $errors[] = 'Xác nhận mật khẩu không khớp.';
        if ($this->users->emailExists($email))  $errors[] = 'Email này đã được đăng ký.';

        if ($errors) {
            $this->flash('error', implode('<br>', $errors));
            $this->redirect('/register');
            return;
        }

        try {
            $userId = $this->users->register([
                'email'     => $email,
                'password'  => $password,
                'full_name' => $fullName,
                'phone'     => $phone ?: null,
            ]);

            $user = $this->users->findWithRole($userId);
            Session::login($user);

            $this->flash('success', 'Đăng ký thành công! Chào mừng bạn đến với GlowBook.');
            $this->redirect('/customer/dashboard');
        } catch (\Exception $e) {
            $this->flash('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
            $this->redirect('/register');
        }
    }

    // GET /logout
    public function logout(): void
    {
        Session::destroy();
        $this->redirect('/login');
    }

    // ---- Private helper ------------------------------------
    private function dashboardUrl(string $role = ''): string
    {
        $role = $role ?: Session::userRole();
        return match($role) {
            'PLATFORM_ADMIN', 'COMPLIANCE', 'SUPPORT' => '/admin/dashboard',
            'BUSINESS_OWNER', 'BRANCH_MANAGER', 'RECEPTIONIST', 'STAFF' => '/salon/dashboard',
            default => '/customer/dashboard',
        };
    }
}
