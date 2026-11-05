<?php
declare(strict_types=1);

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, full_name, email, password_hash, phone, house_no, role, status
             FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => mb_strtolower(trim($email))]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, full_name, email, phone, house_no, role, status, created_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function createResident(
        string $fullName,
        string $email,
        string $password,
        string $phone,
        string $houseNo
    ): int {
        $statement = $this->db->prepare(
            "INSERT INTO users (full_name, email, password_hash, phone, house_no, role, status)
             VALUES (:full_name, :email, :password_hash, :phone, :house_no, 'resident', 'pending')"
        );
        $statement->execute([
            'full_name' => trim($fullName),
            'email' => mb_strtolower(trim($email)),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => trim($phone) !== '' ? trim($phone) : null,
            'house_no' => trim($houseNo),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function pendingResidents(): array
    {
        return $this->db->query(
            "SELECT id, full_name, email, house_no, created_at
             FROM users WHERE status = 'pending' AND role = 'resident'
             ORDER BY created_at ASC"
        )->fetchAll();
    }

    public function setResidentStatus(int $userId, string $status): bool
    {
        $statement = $this->db->prepare(
            "UPDATE users SET status = :status
             WHERE id = :id AND role = 'resident' AND status = 'pending'"
        );
        $statement->execute(['status' => $status, 'id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function notify(int $userId, string $type, string $message, ?string $link = null): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, message, link)
             VALUES (:user_id, :type, :message, :link)'
        );
        $statement->execute([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'link' => $link,
        ]);
    }
}
