<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$requestPath = str_replace('\\', '/', $requestPath);

if (str_contains($requestPath, "\0")) {
    http_response_code(400);
    exit('Bad request.');
}

$segments = array_filter(explode('/', $requestPath), static fn(string $part): bool => $part !== '');
foreach ($segments as $segment) {
    if (in_array(strtolower($segment), ['model', 'controller', 'tools', 'storage'], true)
        || str_starts_with($segment, '.')) {
        http_response_code(404);
        exit('Not found.');
    }
}

if ($requestPath === '/' || $requestPath === '/index.php') {
    require __DIR__ . '/index.php';
    exit;
}

$publicStaticRoots = [
    '/Assets/' => realpath(__DIR__ . '/Assets'),
];
$publicHtml = $requestPath === '/Views/index.html';
$isPublicAsset = false;

foreach ($publicStaticRoots as $urlPrefix => $directory) {
    if ($directory === false || !str_starts_with($requestPath, $urlPrefix)) {
        continue;
    }

    $assetPath = realpath($directory . DIRECTORY_SEPARATOR . substr($requestPath, strlen($urlPrefix)));
    if ($assetPath !== false
        && str_starts_with($assetPath, $directory . DIRECTORY_SEPARATOR)
        && is_file($assetPath)
        && in_array(
            strtolower(pathinfo($assetPath, PATHINFO_EXTENSION)),
            ['css', 'js', 'jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf'],
            true
        )) {
        $isPublicAsset = true;
        break;
    }
}

if ($publicHtml) {
    require __DIR__ . '/index.php';
    exit;
}

if ($isPublicAsset) {
    return false;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found.';
