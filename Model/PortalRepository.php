<?php
declare(strict_types=1);

final class PortalRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function posts(): array
    {
        return $this->db->query(
            'SELECT p.id, p.user_id, p.title, p.body, p.post_image, p.category, p.status,
                    p.is_announcement, p.is_pinned, p.view_count, p.created_at,
                    u.full_name, u.house_no,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
                    (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id) AS reaction_count
             FROM posts p
             INNER JOIN users u ON u.id = p.user_id
             WHERE u.status = \'approved\'
             ORDER BY p.is_pinned DESC, p.created_at DESC
             LIMIT 100'
        )->fetchAll();
    }

    public function incrementPostViews(array $postIds): void
    {
        if ($postIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $statement = $this->db->prepare(
            "UPDATE posts SET view_count = view_count + 1 WHERE id IN ($placeholders)"
        );
        $statement->execute(array_values($postIds));
    }

    public function commentsForPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $statement = $this->db->prepare(
            "SELECT c.id, c.post_id, c.user_id, c.body, c.created_at, u.full_name
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.post_id IN ($placeholders) AND u.status = 'approved'
             ORDER BY c.created_at ASC"
        );
        $statement->execute($postIds);
        $comments = [];

        foreach ($statement->fetchAll() as $comment) {
            $comments[(int) $comment['post_id']][] = $comment;
        }

        return $comments;
    }

    public function reactedPostIds(int $userId, array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $statement = $this->db->prepare(
            "SELECT post_id FROM reactions WHERE user_id = ? AND post_id IN ($placeholders)"
        );
        $statement->execute(array_merge([$userId], $postIds));

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function createPost(
        int $userId,
        string $title,
        string $body,
        string $category,
        ?string $imagePath = null
    ): int {
        $statement = $this->db->prepare(
            "INSERT INTO posts (user_id, title, body, post_image, category, status)
             VALUES (:user_id, :title, :body, :post_image, :category, 'open')"
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => trim($title),
            'body' => trim($body),
            'post_image' => $imagePath,
            'category' => $category,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createAnnouncement(int $userId, string $title, string $body, bool $pinned): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO posts (user_id, title, body, category, is_announcement, is_pinned)
             VALUES (:user_id, :title, :body, 'concern', TRUE, :is_pinned)"
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => trim($title),
            'body' => trim($body),
            'is_pinned' => $pinned,
        ]);
        $postId = (int) $this->db->lastInsertId();

        $notification = $this->db->prepare(
            "INSERT INTO notifications (user_id, type, message, link)
             SELECT id, 'announcement', :message, CONCAT('?page=home#post-', :post_id)
             FROM users
             WHERE status = 'approved' AND id <> :author_id"
        );
        $notification->execute([
            'message' => 'New community announcement: ' . trim($title),
            'post_id' => $postId,
            'author_id' => $userId,
        ]);
    }

    public function addComment(int $postId, int $userId, string $body): int
    {
        $statement = $this->db->prepare(
            "INSERT INTO comments (post_id, user_id, body)
             SELECT :post_id, :user_id, :body
             FROM posts p INNER JOIN users u ON u.id = p.user_id
             WHERE p.id = :post_check AND u.status = 'approved'"
        );
        $statement->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'body' => trim($body),
            'post_check' => $postId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException('That community post is not available.');
        }

        $ownerStatement = $this->db->prepare('SELECT user_id, title FROM posts WHERE id = :id');
        $ownerStatement->execute(['id' => $postId]);
        $owner = $ownerStatement->fetch();
        if ($owner && (int) $owner['user_id'] !== $userId) {
            $this->notify(
                (int) $owner['user_id'],
                'reply',
                'A neighbor commented on your post: ' . $owner['title'],
                '?page=home#post-' . $postId
            );
        }

        return (int) $this->db->lastInsertId();
    }

    public function toggleReaction(int $postId, int $userId): array
    {
        $existing = $this->db->prepare(
            'SELECT id FROM reactions WHERE post_id = :post_id AND user_id = :user_id'
        );
        $existing->execute(['post_id' => $postId, 'user_id' => $userId]);
        $reactionId = $existing->fetchColumn();

        if ($reactionId !== false) {
            $delete = $this->db->prepare('DELETE FROM reactions WHERE id = :id');
            $delete->execute(['id' => $reactionId]);
            $reacted = false;
        } else {
            $insert = $this->db->prepare(
                "INSERT INTO reactions (post_id, user_id, type)
                 SELECT :post_id, :user_id, 'upvote'
                 FROM posts p INNER JOIN users u ON u.id = p.user_id
                 WHERE p.id = :post_check AND u.status = 'approved'"
            );
            $insert->execute([
                'post_id' => $postId,
                'user_id' => $userId,
                'post_check' => $postId,
            ]);
            if ($insert->rowCount() !== 1) {
                throw new DomainException('That community post is not available.');
            }
            $reacted = true;
        }

        $count = $this->db->prepare('SELECT COUNT(*) FROM reactions WHERE post_id = :post_id');
        $count->execute(['post_id' => $postId]);

        return ['reacted' => $reacted, 'count' => (int) $count->fetchColumn()];
    }

    public function feesForResident(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT f.id, f.title, f.description, f.amount, f.due_date, f.type,
                    COALESCE(last_payment.status, 'due') AS payment_status,
                    last_payment.reference_no, last_payment.remarks, last_payment.created_at AS payment_created_at
             FROM fees f
             LEFT JOIN (
               SELECT p1.fee_id, p1.status, p1.reference_no, p1.remarks, p1.created_at
               FROM payments p1
               INNER JOIN (
                 SELECT fee_id, MAX(id) AS latest_id
                 FROM payments WHERE user_id = :user_id
                 GROUP BY fee_id
               ) latest ON latest.latest_id = p1.id
             ) last_payment ON last_payment.fee_id = f.id
             ORDER BY f.due_date DESC, f.id DESC"
        );
        $statement->execute(['user_id' => $userId]);
        $fees = $statement->fetchAll();

        foreach ($fees as &$fee) {
            if ($fee['payment_status'] === 'due') {
                $fee['payment_status'] = $fee['due_date'] < date('Y-m-d') ? 'overdue' : 'due';
            }
            if ($fee['payment_status'] === 'verified') {
                $fee['payment_status'] = 'paid';
            }
        }
        unset($fee);

        return $fees;
    }

    public function submitPayment(
        int $userId,
        int $feeId,
        string $method,
        string $reference,
        ?string $proofPath
    ): void {
        $this->db->beginTransaction();
        try {
            $feeStatement = $this->db->prepare('SELECT amount FROM fees WHERE id = :id FOR UPDATE');
            $feeStatement->execute(['id' => $feeId]);
            $amount = $feeStatement->fetchColumn();
            if ($amount === false) {
                throw new DomainException('The selected fee no longer exists.');
            }

            $latestPayment = $this->db->prepare(
                'SELECT status FROM payments WHERE user_id = :user_id AND fee_id = :fee_id
                 ORDER BY id DESC LIMIT 1'
            );
            $latestPayment->execute(['user_id' => $userId, 'fee_id' => $feeId]);
            $latestStatus = $latestPayment->fetchColumn();
            if (in_array($latestStatus, ['pending', 'verified'], true)) {
                throw new DomainException(
                    $latestStatus === 'pending'
                        ? 'A payment for this fee is already awaiting review.'
                        : 'This fee has already been paid.'
                );
            }

            $insert = $this->db->prepare(
                "INSERT INTO payments (user_id, fee_id, amount, method, reference_no, proof_image, status)
                 VALUES (:user_id, :fee_id, :amount, :method, :reference_no, :proof_image, 'pending')"
            );
            $insert->execute([
                'user_id' => $userId,
                'fee_id' => $feeId,
                'amount' => $amount,
                'method' => $method,
                'reference_no' => trim($reference) !== '' ? trim($reference) : null,
                'proof_image' => $proofPath,
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function paymentHistory(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT p.id, p.amount, p.method, p.reference_no, p.status, p.remarks,
                    p.proof_image, p.created_at, f.title
             FROM payments p
             INNER JOIN fees f ON f.id = p.fee_id
             WHERE p.user_id = :user_id
             ORDER BY p.created_at DESC LIMIT 50'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function notifications(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT id, type, message, link, is_read, created_at
             FROM notifications WHERE user_id = :user_id
             ORDER BY created_at DESC LIMIT 100'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function markNotificationRead(int $notificationId, int $userId): void
    {
        $statement = $this->db->prepare(
            'UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    public function markAllNotificationsRead(int $userId): void
    {
        $statement = $this->db->prepare(
            'UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id AND is_read = FALSE'
        );
        $statement->execute(['user_id' => $userId]);
    }

    public function createFee(
        int $createdBy,
        string $title,
        string $description,
        string $amount,
        string $dueDate,
        string $type
    ): void {
        $statement = $this->db->prepare(
            'INSERT INTO fees (title, description, amount, due_date, type, created_by)
             VALUES (:title, :description, :amount, :due_date, :type, :created_by)'
        );
        $statement->execute([
            'title' => trim($title),
            'description' => trim($description) !== '' ? trim($description) : null,
            'amount' => $amount,
            'due_date' => $dueDate,
            'type' => $type,
            'created_by' => $createdBy,
        ]);
    }

    public function pendingPayments(): array
    {
        return $this->db->query(
            "SELECT p.id, p.user_id, p.fee_id, p.amount, p.method, p.reference_no,
                    p.proof_image, p.created_at, u.full_name, u.house_no, f.title
             FROM payments p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN fees f ON f.id = p.fee_id
             WHERE p.status = 'pending'
             ORDER BY p.created_at ASC"
        )->fetchAll();
    }

    public function reviewPayment(int $paymentId, int $reviewerId, string $status, string $remarks): bool
    {
        $statement = $this->db->prepare(
            "UPDATE payments
             SET status = :status, remarks = :remarks, verified_by = :verified_by,
                 verified_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'pending'"
        );
        $statement->execute([
            'status' => $status,
            'remarks' => trim($remarks) !== '' ? trim($remarks) : null,
            'verified_by' => $reviewerId,
            'id' => $paymentId,
        ]);
        if ($statement->rowCount() !== 1) {
            return false;
        }

        $owner = $this->db->prepare(
            'SELECT user_id, fee_id FROM payments WHERE id = :id'
        );
        $owner->execute(['id' => $paymentId]);
        $payment = $owner->fetch();
        if ($payment) {
            $fee = $this->db->prepare('SELECT title FROM fees WHERE id = :id');
            $fee->execute(['id' => $payment['fee_id']]);
            $title = (string) $fee->fetchColumn();
            $this->notify(
                (int) $payment['user_id'],
                'payment',
                'Your payment for ' . $title . ' was ' . $status . '.',
                '?page=bills'
            );
        }

        return true;
    }

    public function createExpense(
        int $recordedBy,
        string $description,
        string $amount,
        string $category,
        string $expenseDate,
        ?string $receiptPath
    ): void {
        $statement = $this->db->prepare(
            'INSERT INTO expenses (description, amount, category, expense_date, receipt_image, recorded_by)
             VALUES (:description, :amount, :category, :expense_date, :receipt_image, :recorded_by)'
        );
        $statement->execute([
            'description' => trim($description),
            'amount' => $amount,
            'category' => trim($category),
            'expense_date' => $expenseDate,
            'receipt_image' => $receiptPath,
            'recorded_by' => $recordedBy,
        ]);
    }

    public function expenses(): array
    {
        return $this->db->query(
            'SELECT e.id, e.description, e.amount, e.category, e.expense_date,
                    e.receipt_image, e.created_at, u.full_name
             FROM expenses e INNER JOIN users u ON u.id = e.recorded_by
             ORDER BY e.expense_date DESC, e.id DESC LIMIT 100'
        )->fetchAll();
    }

    public function findReceipt(int $paymentId, ?int $userId, bool $canReview): ?array
    {
        $sql = 'SELECT proof_image, user_id FROM payments WHERE id = :id';
        if (!$canReview) {
            $sql .= ' AND user_id = :user_id';
        }
        $statement = $this->db->prepare($sql);
        $params = ['id' => $paymentId];
        if (!$canReview) {
            $params['user_id'] = $userId;
        }
        $statement->execute($params);
        $receipt = $statement->fetch();

        return $receipt === false ? null : $receipt;
    }

    private function notify(int $userId, string $type, string $message, ?string $link): void
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
