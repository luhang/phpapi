<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/Response.php';
require dirname(__DIR__) . '/src/WatchlistRepository.php';
require dirname(__DIR__) . '/src/WatchlistController.php';

use App\Database;
use App\Response;
use App\WatchlistController;
use App\WatchlistRepository;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri    = rtrim($uri, '/') ?: '/';

    // Support both /api/watchlist and /watchlist (and optional /index.php prefix)
    $uri = preg_replace('#^/index\.php#', '', $uri) ?: '/';
    $uri = preg_replace('#^/api#', '', $uri) ?: '/';

    $controller = new WatchlistController(new WatchlistRepository(Database::connection()));
    $body       = readJsonBody();

    // GET /watchlist
    if ($method === 'GET' && $uri === '/watchlist') {
        $controller->index($_GET);
    }

    // GET /watchlist/{exchange_code}/{ticker}
    if ($method === 'GET' && preg_match('#^/watchlist/([^/]+)/([^/]+)$#', $uri, $m)) {
        $controller->show(rawurldecode($m[1]), rawurldecode($m[2]));
    }

    // POST /watchlist
    if ($method === 'POST' && $uri === '/watchlist') {
        $controller->store($body);
    }

    // DELETE /watchlist/{exchange_code}/{ticker}
    if ($method === 'DELETE' && preg_match('#^/watchlist/([^/]+)/([^/]+)$#', $uri, $m)) {
        $controller->destroy(rawurldecode($m[1]), rawurldecode($m[2]));
    }

    // Health check
    if ($method === 'GET' && ($uri === '/' || $uri === '/health')) {
        Response::success([
            'service' => 'stock-watchlist-api',
            'status'  => 'up',
        ]);
    }

    Response::error('Not found', 404);
} catch (Throwable $e) {
    Response::error(
        'Internal server error',
        500,
        getenv('APP_DEBUG') === '1' ? $e->getMessage() : null
    );
}

/**
 * @return array<string, mixed>
 */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        Response::error('Invalid JSON body', 400);
    }

    return $decoded;
}
