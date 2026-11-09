<?php
declare(strict_types=1);

final class AuthController
{
    public function __construct(
        private readonly PDO $db,
        private readonly UserRepository $users
    ) {
    }

    public function login(): never
    {
        verify_csrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            flash('error', 'The email or password you entered is incorrect.');
            redirect_to('index.php?action=login');
        }
        if ($user['status'] !== 'approved') {
            flash(
                'error',
                $user['status'] === 'pending'
                    ? 'Your account is waiting for HOA administrator approval.'
                    : 'This account has not been approved. Contact your HOA administrator.'
            );
            redirect_to('index.php?action=login');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'house_no' => $user['house_no'],
            'role' => $user['role'],
        ];
        csrf_token();
        redirect_to('index.php');
    }

    public function register(): never
    {
        verify_csrf();
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $houseNo = trim((string) ($_POST['house_no'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($fullName === '' || mb_strlen($fullName) > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254
            || mb_strlen($phone) > 32 || $houseNo === '' || mb_strlen($houseNo) > 100
            || strlen($password) < 10 || strlen($password) > 200) {
            flash('error', 'Please complete each field. Passwords must be at least 10 characters.');
            redirect_to('index.php?action=register');
        }
        if ($this->users->findByEmail($email) !== null) {
            flash('error', 'An account already uses that email address.');
            redirect_to('index.php?action=register');
        }

        try {
            $this->users->createResident($fullName, $email, $password, $phone, $houseNo);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('error', 'An account already uses that email address.');
            redirect_to('index.php?action=register');
        }
        flash('success', 'Registration submitted. You can sign in once an HOA administrator approves your account.');
        redirect_to('index.php?action=login');
    }

    public function logout(): never
    {
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        redirect_to('index.php?action=login');
    }
}
