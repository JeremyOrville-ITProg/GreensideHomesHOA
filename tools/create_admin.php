<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Model/Database.php';

try {
    $db = Database::connection();
    $adminCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount > 0) {
        fwrite(STDERR, "An administrator account already exists. This tool only creates the first admin.\n");
        exit(1);
    }

    $fullName = trim((string) readline('Administrator full name: '));
    $email = mb_strtolower(trim((string) readline('Administrator email: ')));
    $houseNo = trim((string) readline('House / block / lot: '));
    $password = (string) readline('Password (minimum 12 characters): ');

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $houseNo === '' || strlen($password) < 12) {
        fwrite(STDERR, "Invalid input. Name, valid email, house details, and a 12-character password are required.\n");
        exit(1);
    }

    $statement = $db->prepare(
        "INSERT INTO users (full_name, email, password_hash, house_no, role, status)
         VALUES (:full_name, :email, :password_hash, :house_no, 'admin', 'approved')"
    );
    $statement->execute([
        'full_name' => $fullName,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'house_no' => $houseNo,
    ]);

    fwrite(STDOUT, "Administrator account created. Sign in at index.php?action=login.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Could not create the administrator: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
