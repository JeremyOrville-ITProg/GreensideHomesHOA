<?php
declare(strict_types=1);

final class PortalController
{
    private UserRepository $users;
    private PortalRepository $portal;
    private AuthController $auth;

    public function __construct(private readonly PDO $db)
    {
        $this->users = new UserRepository($db);
        $this->portal = new PortalRepository($db);
        $this->auth = new AuthController($db, $this->users);
    }

    public function dispatch(): void
    {
        $action = (string) ($_GET['action'] ?? 'dashboard');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($action === 'login' && $method === 'POST') {
            $this->auth->login();
        }
        if ($action === 'register' && $method === 'POST') {
            $this->auth->register();
        }
        if ($action === 'logout' && $method === 'POST') {
            $this->auth->logout();
        }
        if ($action === 'login' || $action === 'register') {
            $this->showAuthPage($action);
            return;
        }
        if (!current_user()) {
            redirect_to('index.php?action=login');
        }

        if ($action === 'comment' && $method === 'POST') {
            $this->addComment();
            return;
        }
        if ($action === 'reaction' && $method === 'POST') {
            $this->toggleReaction();
            return;
        }
        if ($action === 'notification-read' && $method === 'POST') {
            $this->markNotificationsRead();
            return;
        }
        if ($action === 'receipt' && $method === 'GET') {
            $this->showReceipt();
            return;
        }
        if ($method === 'POST') {
            $this->handlePortalPost($action);
            return;
        }

        $this->showDashboard();
    }

    private function showAuthPage(string $page): void
    {
        if (current_user()) {
            redirect_to('index.php');
        }

        $flashMessage = consume_flash();
        require __DIR__ . '/../Views/auth.php';
    }

    private function showDashboard(): void
    {
        $user = current_user();
        $freshUser = $this->users->findById((int) $user['id']);
        if ($freshUser === null || $freshUser['status'] !== 'approved') {
            $_SESSION = [];
            session_regenerate_id(true);
            flash('error', 'Your account is no longer active. Contact your HOA administrator.');
            redirect_to('index.php?action=login');
        }
        $_SESSION['user']['full_name'] = $freshUser['full_name'];
        $_SESSION['user']['email'] = $freshUser['email'];
        $_SESSION['user']['house_no'] = $freshUser['house_no'];
        $_SESSION['user']['role'] = $freshUser['role'];
        $user = $_SESSION['user'];

        $page = (string) ($_GET['page'] ?? 'home');
        $allowedPages = ['home', 'bills', 'alerts', 'profile', 'create', 'admin'];
        if (!in_array($page, $allowedPages, true)
            || ($page === 'admin' && !$this->canAccessAdmin($user))) {
            $page = 'home';
        }

        $posts = $this->portal->posts();
        $postIds = array_map(static fn(array $post): int => (int) $post['id'], $posts);
        if ($page === 'home' && $postIds !== []) {
            $viewedIds = $_SESSION['viewed_post_ids'] ?? [];
            $newViewIds = array_values(array_filter(
                $postIds,
                static fn(int $postId): bool => !isset($viewedIds[$postId])
            ));
            if ($newViewIds !== []) {
                $this->portal->incrementPostViews($newViewIds);
                foreach ($newViewIds as $postId) {
                    $viewedIds[$postId] = true;
                }
                if (count($viewedIds) > 1000) {
                    $viewedIds = array_slice($viewedIds, -1000, null, true);
                }
                $_SESSION['viewed_post_ids'] = $viewedIds;

                foreach ($posts as &$post) {
                    if (in_array((int) $post['id'], $newViewIds, true)) {
                        $post['view_count'] = (int) $post['view_count'] + 1;
                    }
                }
                unset($post);
            }
        }
        $commentsByPost = $this->portal->commentsForPosts($postIds);
        $reactedPostIds = $this->portal->reactedPostIds((int) $user['id'], $postIds);
        $fees = $this->portal->feesForResident((int) $user['id']);
        $paymentHistory = $this->portal->paymentHistory((int) $user['id']);
        $notifications = $this->portal->notifications((int) $user['id']);
        $unreadCount = count(array_filter(
            $notifications,
            static fn(array $notification): bool => !(bool) $notification['is_read']
        ));
        $pendingResidents = $this->canManageResidents($user) ? $this->users->pendingResidents() : [];
        $pendingPayments = $this->canReviewPayments($user) ? $this->portal->pendingPayments() : [];
        $expenses = $this->canManageExpenses($user) ? $this->portal->expenses() : [];
        $flashMessage = consume_flash();

        require __DIR__ . '/../Views/dashboard.php';
    }

    private function handlePortalPost(string $action): never
    {
        verify_csrf();
        $user = current_user();
        $userId = (int) $user['id'];

        switch ($action) {
            case 'create-post':
                $title = trim((string) ($_POST['title'] ?? ''));
                $body = trim((string) ($_POST['body'] ?? ''));
                $category = (string) ($_POST['category'] ?? '');
                if ($title === '' || mb_strlen($title) > 200 || $body === ''
                    || mb_strlen($body) > 10000
                    || !in_array($category, ['concern', 'question', 'suggestion'], true)) {
                    flash('error', 'Enter a post title and message and choose a valid category.');
                    redirect_to('index.php?page=create');
                }
                $this->portal->createPost($userId, $title, $body, $category);
                flash('success', 'Your post was shared with the community.');
                redirect_to('index.php?page=home');

            case 'create-announcement':
                $this->requireRole(['admin']);
                $title = trim((string) ($_POST['title'] ?? ''));
                $body = trim((string) ($_POST['body'] ?? ''));
                if ($title === '' || mb_strlen($title) > 200 || $body === ''
                    || mb_strlen($body) > 10000) {
                    flash('error', 'Enter an announcement title and message.');
                    redirect_to('index.php?page=admin');
                }
                $this->portal->createAnnouncement(
                    $userId,
                    $title,
                    $body,
                    isset($_POST['is_pinned'])
                );
                flash('success', 'The announcement was published and residents were notified.');
                redirect_to('index.php?page=home');

            case 'submit-payment':
                $feeId = filter_var($_POST['fee_id'] ?? null, FILTER_VALIDATE_INT);
                $method = (string) ($_POST['method'] ?? '');
                $reference = trim((string) ($_POST['reference_no'] ?? ''));
                if (!$feeId || !in_array($method, ['cash', 'bank', 'e-wallet'], true)
                    || mb_strlen($reference) > 150) {
                    flash('error', 'Enter a valid payment method and reference number.');
                    redirect_to('index.php?page=bills');
                }
                $proofPath = $this->storeUpload($_FILES['proof_image'] ?? [], 'proofs', true);
                try {
                    $this->portal->submitPayment($userId, $feeId, $method, $reference, $proofPath);
                } catch (Throwable $exception) {
                    if ($proofPath !== null) {
                        @unlink(__DIR__ . '/../storage/' . $proofPath);
                    }
                    throw $exception;
                }
                flash('success', 'Payment proof submitted and is waiting for treasurer review.');
                redirect_to('index.php?page=bills');

            case 'create-fee':
                $this->requireRole(['admin', 'treasurer']);
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $amount = $this->validatedAmount($_POST['amount'] ?? null);
                $dueDate = (string) ($_POST['due_date'] ?? '');
                $type = (string) ($_POST['type'] ?? '');
                if ($title === '' || mb_strlen($title) > 150 || $amount === null
                    || !$this->validDate($dueDate)
                    || !in_array($type, ['monthly', 'special'], true)) {
                    flash('error', 'Enter a title, valid positive amount, due date, and fee type.');
                    redirect_to('index.php?page=admin');
                }
                $this->portal->createFee($userId, $title, $description, $amount, $dueDate, $type);
                flash('success', 'The fee has been created.');
                redirect_to('index.php?page=admin');

            case 'review-payment':
                $this->requireRole(['admin', 'treasurer']);
                $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($_POST['status'] ?? '');
                if (!$paymentId || !in_array($status, ['verified', 'rejected'], true)) {
                    flash('error', 'The payment review details are invalid.');
                    redirect_to('index.php?page=admin');
                }
                if (!$this->portal->reviewPayment(
                    $paymentId,
                    $userId,
                    $status,
                    (string) ($_POST['remarks'] ?? '')
                )) {
                    flash('error', 'That payment was already reviewed or no longer exists.');
                } else {
                    flash('success', 'Payment marked as ' . $status . '.');
                }
                redirect_to('index.php?page=admin');

            case 'approve-resident':
                $this->requireRole(['admin']);
                $residentId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($_POST['status'] ?? '');
                if (!$residentId || !in_array($status, ['approved', 'rejected'], true)) {
                    flash('error', 'The resident approval details are invalid.');
                    redirect_to('index.php?page=admin');
                }
                if (!$this->users->setResidentStatus($residentId, $status)) {
                    flash('error', 'That resident was already reviewed or no longer exists.');
                    redirect_to('index.php?page=admin');
                }
                if ($status === 'approved') {
                    $this->users->notify($residentId, 'announcement', 'Your Greenside Homes account has been approved.', '?page=home');
                }
                flash('success', 'Resident request marked as ' . $status . '.');
                redirect_to('index.php?page=admin');

            case 'create-expense':
                $this->requireRole(['admin', 'treasurer']);
                $description = trim((string) ($_POST['description'] ?? ''));
                $amount = $this->validatedAmount($_POST['amount'] ?? null);
                $category = trim((string) ($_POST['category'] ?? ''));
                $expenseDate = (string) ($_POST['expense_date'] ?? '');
                if ($description === '' || mb_strlen($description) > 10000 || $amount === null
                    || $category === '' || mb_strlen($category) > 100
                    || !$this->validDate($expenseDate)) {
                    flash('error', 'Enter an expense description, valid amount, category, and date.');
                    redirect_to('index.php?page=admin');
                }
                $receiptPath = $this->storeUpload($_FILES['receipt_image'] ?? [], 'expenses', false);
                try {
                    $this->portal->createExpense(
                        $userId,
                        $description,
                        $amount,
                        $category,
                        $expenseDate,
                        $receiptPath
                    );
                } catch (Throwable $exception) {
                    if ($receiptPath !== null) {
                        @unlink(__DIR__ . '/../storage/' . $receiptPath);
                    }
                    throw $exception;
                }
                flash('success', 'Expense recorded.');
                redirect_to('index.php?page=admin');
        }

        http_response_code(404);
        echo 'Not found.';
        exit;
    }

    private function addComment(): never
    {
        verify_csrf();
        $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
        $body = trim((string) ($_POST['body'] ?? ''));
        if (!$postId || $body === '' || mb_strlen($body) > 2000) {
            json_response(['error' => 'Enter a comment of up to 2,000 characters.'], 422);
        }
        $commentId = $this->portal->addComment($postId, (int) current_user()['id'], $body);
        json_response([
            'success' => true,
            'id' => $commentId,
            'body' => $body,
            'full_name' => current_user()['full_name'],
            'created_at' => date('Y-m-d H:i:s'),
        ], 201);
    }

    private function toggleReaction(): never
    {
        verify_csrf();
        $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$postId) {
            json_response(['error' => 'Choose a valid post.'], 422);
        }
        json_response([
            'success' => true,
            ...$this->portal->toggleReaction($postId, (int) current_user()['id']),
        ]);
    }

    private function markNotificationsRead(): never
    {
        verify_csrf();
        $userId = (int) current_user()['id'];
        if (isset($_POST['notification_id'])) {
            $id = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);
            if (!$id) {
                json_response(['error' => 'Choose a valid notification.'], 422);
            }
            $this->portal->markNotificationRead($id, $userId);
        } else {
            $this->portal->markAllNotificationsRead($userId);
        }
        json_response(['success' => true]);
    }

    private function showReceipt(): never
    {
        $user = current_user();
        $isReviewer = $this->canReviewPayments($user);
        $receipt = $this->portal->findReceipt(
            (int) ($_GET['id'] ?? 0),
            (int) $user['id'],
            $isReviewer
        );
        if ($receipt === null || !$receipt['proof_image']) {
            http_response_code(404);
            exit('Receipt not found.');
        }
        $path = realpath(__DIR__ . '/../storage/' . $receipt['proof_image']);
        $storage = realpath(__DIR__ . '/../storage');
        if ($path === false || $storage === false
            || !str_starts_with($path, $storage . DIRECTORY_SEPARATOR)
            || !is_file($path)) {
            http_response_code(404);
            exit('Receipt not found.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            http_response_code(415);
            exit('Unsupported receipt type.');
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="payment-receipt"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function storeUpload(array $file, string $folder, bool $required): ?string
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                throw new DomainException('Choose a file to upload.');
            }
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new DomainException('The file upload failed. Please choose the file again.');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new DomainException('Files must be 5 MB or smaller.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new DomainException('Upload a JPG, PNG, WebP, or PDF file.');
        }
        if (str_starts_with($mime, 'image/')) {
            $image = getimagesize($file['tmp_name']);
            if ($image === false || $image['mime'] !== $mime) {
                throw new DomainException('The uploaded image is invalid.');
            }
        }

        $relativeDirectory = $folder;
        $directory = __DIR__ . '/../storage/' . $relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to prepare private file storage.');
        }
        $fileName = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $fileName)) {
            throw new RuntimeException('Unable to save the uploaded file.');
        }

        return $relativeDirectory . '/' . $fileName;
    }

    private function validatedAmount(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $amount = trim((string) $value);
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $amount) || (float) $amount <= 0) {
            return null;
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function requireRole(array $roles): void
    {
        if (!in_array(current_user()['role'], $roles, true)) {
            http_response_code(403);
            exit('You do not have permission to perform this action.');
        }
    }

    private function canManageResidents(array $user): bool
    {
        return $user['role'] === 'admin';
    }

    private function canReviewPayments(array $user): bool
    {
        return in_array($user['role'], ['admin', 'treasurer'], true);
    }

    private function canManageExpenses(array $user): bool
    {
        return in_array($user['role'], ['admin', 'treasurer'], true);
    }

    private function canAccessAdmin(array $user): bool
    {
        return in_array($user['role'], ['admin', 'treasurer'], true);
    }
}
