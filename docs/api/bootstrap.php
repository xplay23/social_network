<?php

declare(strict_types=1);

function config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $configFile = __DIR__ . '/config.php';
    if (is_file($configFile)) {
        $config = require $configFile;
        return $config;
    }

    $config = [
        'database' => [
            'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('MYSQL_PORT') ?: 3306),
            'name' => getenv('MYSQL_DATABASE') ?: 'circle_social',
            'user' => getenv('MYSQL_USER') ?: '',
            'password' => getenv('MYSQL_PASSWORD') ?: '',
        ],
        'jwt_secret' => getenv('JWT_SECRET') ?: '',
        'client_origins' => array_filter(array_map('trim', explode(',', getenv('CLIENT_ORIGIN') ?: 'http://localhost:5173'))),
    ];
    return $config;
}

function database(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config()['database'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestBody(): array
{
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($body)) {
        jsonResponse(['message' => 'Некорректный JSON'], 400);
    }
    return $body;
}

function execute(string $sql, array $params = []): PDOStatement
{
    $statement = database()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64UrlDecode(string $value): string|false
{
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function createToken(string $userId): string
{
    $secret = (string) config()['jwt_secret'];
    if (strlen($secret) < 32) {
        throw new RuntimeException('JWT secret must contain at least 32 characters');
    }
    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64UrlEncode(json_encode([
        'sub' => $userId,
        'iss' => 'circle-api',
        'iat' => time(),
        'exp' => time() + 604800,
    ]));
    $signature = base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$signature";
}

function authenticatedUserId(): string
{
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        jsonResponse(['message' => 'Требуется авторизация'], 401);
    }

    $parts = explode('.', $matches[1]);
    if (count($parts) !== 3) {
        jsonResponse(['message' => 'Недействительная сессия'], 401);
    }
    [$header, $payload, $signature] = $parts;
    $expected = base64UrlEncode(hash_hmac('sha256', "$header.$payload", (string) config()['jwt_secret'], true));
    $claims = json_decode((string) base64UrlDecode($payload), true);
    if (!hash_equals($expected, $signature) || !is_array($claims) || ($claims['iss'] ?? '') !== 'circle-api' || ($claims['exp'] ?? 0) < time() || empty($claims['sub'])) {
        jsonResponse(['message' => 'Сессия истекла. Войдите снова.'], 401);
    }
    return (string) $claims['sub'];
}

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function routePath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path === '/api') {
        return '/';
    }
    if (str_starts_with($path, '/api/')) {
        return substr($path, 4);
    }
    $scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($scriptDirectory !== '' && $scriptDirectory !== '/' && str_starts_with($path, $scriptDirectory)) {
        $path = substr($path, strlen($scriptDirectory));
    }
    return '/' . ltrim($path, '/');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, config()['client_origins'], true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
