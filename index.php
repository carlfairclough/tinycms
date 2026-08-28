<?php

declare(strict_types=1);

/* Public front controller. Application paths are anchored to this file so
 * TinyCMS also works from a subdirectory and does not depend on DOCUMENT_ROOT. */
$root = __DIR__;
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$isInsideDocumentRoot = is_string($documentRoot)
    && $documentRoot !== ''
    && ($root === $documentRoot || str_starts_with($root, rtrim($documentRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR));
if ($isInsideDocumentRoot) {
    $basePath = str_replace('\\', '/', substr($root, strlen($documentRoot)));
} else {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = dirname($scriptName);
}
$basePath = $basePath === '.' || $basePath === '/' ? '' : rtrim($basePath, '/');

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uriPath = is_string($uriPath) ? rawurldecode($uriPath) : '/';

if ($basePath !== '' && ($uriPath === $basePath || str_starts_with($uriPath, $basePath.'/'))) {
    $uriPath = substr($uriPath, strlen($basePath));
}

$request = trim(preg_replace('#/+#', '/', $uriPath) ?? '', '/');

define('ROOT', $root);
define('BASE_PATH', $basePath);
define('REQUEST', $request);

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data: https:; style-src 'self'; script-src 'none'");

require ROOT.'/backend/functions.php';
