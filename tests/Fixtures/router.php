<?php

// A tiny portal stand-in for the built-in cURL transport tests: php -S 127.0.0.1:PORT router.php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
header('Content-Type: application/json');

switch ($path) {
    case '/api/portal/v1/account':
        echo json_encode(['ok' => true, 'data' => [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'seen' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ],
        ]]);
        break;
    case '/api/portal/v1/support-tickets':
        echo json_encode(['ok' => true, 'data' => ['ticket' => [
            'id' => '99999999-8888-4777-8666-555555555555',
            'message' => (string) file_get_contents('php://input'),
            'subject' => $_SERVER['CONTENT_TYPE'] ?? '',
        ]]]);
        break;
    case '/api/portal/v1/usage':
        header('Location: https://evil.example.com/steal', true, 302);
        break;
    case '/api/portal/v1/tenants':
        echo '{"ok":true,"data":{"tenants":[],"padding":"' . str_repeat('x', 3 * 1024 * 1024) . '"}}';
        break;
    case '/api/portal/v1/instances':
        sleep(3);
        echo json_encode(['ok' => true, 'data' => ['instances' => []]]);
        break;
    default:
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Nothing here.']]);
}
