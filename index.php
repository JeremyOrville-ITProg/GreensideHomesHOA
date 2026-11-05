<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $controller = new PortalController(Database::connection());
    $controller->dispatch();
} catch (DomainException $exception) {
    if (str_contains(($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        json_response(['success' => false, 'message' => $exception->getMessage()], 422);
    }
    flash('error', $exception->getMessage());
    redirect_to('index.php?page=' . rawurlencode((string) ($_GET['page'] ?? 'bills')));
} catch (Throwable $exception) {
    error_log(sprintf(
        '[Greenside HOA] %s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
    if (str_contains(($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        $status = http_response_code();
        json_response([
            'success' => false,
            'message' => $status === 419
                ? 'Your session expired. Refresh the page and try again.'
                : 'The portal could not complete your request. Please try again.',
        ], $status >= 400 ? $status : 500);
    }
    http_response_code(500);
    $errorMessage = 'The portal could not complete your request. Check the PHP error log for details.';
    require __DIR__ . '/Views/error.php';
}
