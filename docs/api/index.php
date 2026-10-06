<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = routePath();

try {
    if ($method === 'GET' && $path === '/health') {
        execute('SELECT 1');
        jsonResponse(['status' => 'ok']);
    }

    if ($method === 'POST' && $path === '/auth/register') {
        $body = requestBody();
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $name = trim((string) ($body['name'] ?? ''));
        $username = strtolower(trim((string) ($body['username'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || $name === '' || !preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            jsonResponse(['message' => 'Проверьте email, имя, ник и пароль'], 400);
        }
        $id = uuid();
        try {
            execute('INSERT INTO users (id, email, password_hash, display_name, username) VALUES (?, ?, ?, ?, ?)', [$id, $email, password_hash($password, PASSWORD_DEFAULT), $name, $username]);
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                jsonResponse(['message' => 'Email или ник уже используется'], 409);
            }
            throw $error;
        }
        jsonResponse(['token' => createToken($id), 'user' => ['id' => $id, 'email' => $email]], 201);
    }

    if ($method === 'POST' && $path === '/auth/login') {
        $body = requestBody();
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $user = execute('SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1', [$email])->fetch();
        if (!$user || !password_verify((string) ($body['password'] ?? ''), $user['password_hash'])) {
            jsonResponse(['message' => 'Неверный email или пароль'], 401);
        }
        jsonResponse(['token' => createToken($user['id']), 'user' => ['id' => $user['id'], 'email' => $user['email']]]);
    }

    $userId = authenticatedUserId();

    if ($method === 'GET' && $path === '/auth/me') {
        $user = execute('SELECT id, email FROM users WHERE id = ? LIMIT 1', [$userId])->fetch();
        if (!$user) jsonResponse(['message' => 'Пользователь не найден'], 401);
        jsonResponse($user);
    }

    if ($method === 'GET' && $path === '/profiles') {
        $search = '%' . mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 80) . '%';
        $profiles = execute('SELECT id, display_name, username, avatar_url FROM users WHERE (display_name LIKE ? OR username LIKE ?) AND id <> ? ORDER BY display_name LIMIT 20', [$search, $search, $userId])->fetchAll();
        jsonResponse($profiles);
    }

    if ($method === 'GET' && preg_match('#^/profiles/by-username/([^/]+)$#', $path, $matches)) {
        $profile = execute('SELECT id, display_name, username, avatar_url FROM users WHERE username = ? LIMIT 1', [urldecode($matches[1])])->fetch();
        if (!$profile) jsonResponse(['message' => 'Профиль не найден'], 404);
        jsonResponse($profile);
    }

    if (preg_match('#^/profiles/([a-f0-9-]+)/details$#i', $path, $matches)) {
        if ($method === 'GET') {
            $details = execute('SELECT user_id, bio, location, cover_url, created_at, updated_at FROM profile_details WHERE user_id = ?', [$matches[1]])->fetch();
            jsonResponse($details ?: null);
        }
        if ($method === 'PUT') {
            if ($matches[1] !== $userId) jsonResponse(['message' => 'Нельзя изменить чужой профиль'], 403);
            $body = requestBody();
            execute('INSERT INTO profile_details (user_id, bio, location, cover_url) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE bio = VALUES(bio), location = VALUES(location), cover_url = VALUES(cover_url)', [$userId, $body['bio'] ?? null, $body['location'] ?? null, $body['cover_url'] ?? null]);
            jsonResponse(execute('SELECT * FROM profile_details WHERE user_id = ?', [$userId])->fetch());
        }
    }

    if (preg_match('#^/profiles/([a-f0-9-]+)$#i', $path, $matches)) {
        if ($method === 'GET') {
            $profile = execute('SELECT id, display_name, username, avatar_url, created_at, updated_at FROM users WHERE id = ? LIMIT 1', [$matches[1]])->fetch();
            if (!$profile) jsonResponse(['message' => 'Профиль не найден'], 404);
            jsonResponse($profile);
        }
        if ($method === 'PATCH') {
            if ($matches[1] !== $userId) jsonResponse(['message' => 'Нельзя изменить чужой профиль'], 403);
            $body = requestBody();
            $name = trim((string) ($body['display_name'] ?? ''));
            $username = strtolower(trim((string) ($body['username'] ?? '')));
            if ($name === '' || !preg_match('/^[a-z0-9_]{3,30}$/', $username)) jsonResponse(['message' => 'Некорректные данные профиля'], 400);
            execute('UPDATE users SET display_name = ?, username = ?, avatar_url = ? WHERE id = ?', [$name, $username, $body['avatar_url'] ?? null, $userId]);
            jsonResponse(execute('SELECT id, display_name, username, avatar_url FROM users WHERE id = ?', [$userId])->fetch());
        }
    }

    if ($method === 'GET' && $path === '/posts') {
        $rows = execute('SELECT p.id, p.author_id, p.content, p.image_url, p.created_at, u.display_name, u.username, u.avatar_url, COUNT(DISTINCT l.user_id) AS likes, COUNT(DISTINCT c.id) AS comments, MAX(l.user_id = ?) AS liked FROM posts p JOIN users u ON u.id = p.author_id LEFT JOIN likes l ON l.post_id = p.id LEFT JOIN comments c ON c.post_id = p.id GROUP BY p.id, u.id ORDER BY p.created_at DESC LIMIT 100', [$userId])->fetchAll();
        $posts = array_map(static fn(array $row): array => [
            'id' => $row['id'], 'authorId' => $row['author_id'], 'text' => $row['content'],
            'imageUrl' => $row['image_url'], 'createdAt' => $row['created_at'],
            'likes' => (int) $row['likes'], 'comments' => (int) $row['comments'], 'liked' => (bool) $row['liked'],
            'author' => ['id' => $row['author_id'], 'display_name' => $row['display_name'], 'username' => $row['username'], 'avatar_url' => $row['avatar_url']],
        ], $rows);
        jsonResponse($posts);
    }

    if ($method === 'POST' && $path === '/posts') {
        $body = requestBody();
        $content = trim((string) ($body['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 5000) jsonResponse(['message' => 'Текст должен содержать от 1 до 5000 символов'], 400);
        $id = uuid();
        execute('INSERT INTO posts (id, author_id, content, image_url) VALUES (?, ?, ?, ?)', [$id, $userId, $content, $body['imageUrl'] ?? null]);
        jsonResponse(['id' => $id], 201);
    }

    if ($method === 'DELETE' && preg_match('#^/posts/([a-f0-9-]+)$#i', $path, $matches)) {
        $statement = execute('DELETE FROM posts WHERE id = ? AND author_id = ?', [$matches[1], $userId]);
        if ($statement->rowCount() === 0) jsonResponse(['message' => 'Запись не найдена'], 404);
        jsonResponse(['success' => true]);
    }

    if (preg_match('#^/posts/([a-f0-9-]+)/likes$#i', $path, $matches)) {
        if ($method === 'PUT') execute('INSERT IGNORE INTO likes (post_id, user_id) VALUES (?, ?)', [$matches[1], $userId]);
        elseif ($method === 'DELETE') execute('DELETE FROM likes WHERE post_id = ? AND user_id = ?', [$matches[1], $userId]);
        else jsonResponse(['message' => 'Метод не поддерживается'], 405);
        jsonResponse(['success' => true]);
    }

    if (preg_match('#^/posts/([a-f0-9-]+)/comments$#i', $path, $matches)) {
        if ($method === 'GET') {
            jsonResponse(execute('SELECT c.id, c.post_id, c.author_id, c.content, c.created_at, u.display_name, u.username, u.avatar_url FROM comments c JOIN users u ON u.id = c.author_id WHERE c.post_id = ? ORDER BY c.created_at', [$matches[1]])->fetchAll());
        }
        if ($method === 'POST') {
            $body = requestBody();
            $content = trim((string) ($body['content'] ?? ''));
            if ($content === '' || mb_strlen($content) > 1000) jsonResponse(['message' => 'Комментарий должен содержать от 1 до 1000 символов'], 400);
            $id = uuid();
            execute('INSERT INTO comments (id, post_id, author_id, content) VALUES (?, ?, ?, ?)', [$id, $matches[1], $userId, $content]);
            jsonResponse(['id' => $id], 201);
        }
    }

    if ($method === 'DELETE' && preg_match('#^/comments/([a-f0-9-]+)$#i', $path, $matches)) {
        execute('DELETE FROM comments WHERE id = ? AND author_id = ?', [$matches[1], $userId]);
        jsonResponse(['success' => true]);
    }

    if ($method === 'GET' && $path === '/friendships') {
        $rows = execute('SELECT f.id, f.sender_id, f.receiver_id, f.status, su.display_name AS sender_name, su.username AS sender_username, su.avatar_url AS sender_avatar, ru.display_name AS receiver_name, ru.username AS receiver_username, ru.avatar_url AS receiver_avatar FROM friendships f JOIN users su ON su.id = f.sender_id JOIN users ru ON ru.id = f.receiver_id WHERE f.sender_id = ? OR f.receiver_id = ? ORDER BY f.created_at DESC', [$userId, $userId])->fetchAll();
        jsonResponse(array_map(static fn(array $row): array => [
            'id' => $row['id'], 'sender_id' => $row['sender_id'], 'receiver_id' => $row['receiver_id'], 'status' => $row['status'],
            'sender' => ['id' => $row['sender_id'], 'display_name' => $row['sender_name'], 'username' => $row['sender_username'], 'avatar_url' => $row['sender_avatar']],
            'receiver' => ['id' => $row['receiver_id'], 'display_name' => $row['receiver_name'], 'username' => $row['receiver_username'], 'avatar_url' => $row['receiver_avatar']],
        ], $rows));
    }

    if ($method === 'POST' && $path === '/friendships') {
        $receiverId = (string) (requestBody()['receiverId'] ?? '');
        if ($receiverId === '' || $receiverId === $userId) jsonResponse(['message' => 'Некорректный получатель'], 400);
        if (!execute('SELECT id FROM users WHERE id = ? LIMIT 1', [$receiverId])->fetch()) jsonResponse(['message' => 'Пользователь не найден'], 404);
        if (execute('SELECT id FROM friendships WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1', [$userId, $receiverId, $receiverId, $userId])->fetch()) jsonResponse(['message' => 'Заявка уже существует'], 409);
        $id = uuid();
        execute('INSERT INTO friendships (id, sender_id, receiver_id) VALUES (?, ?, ?)', [$id, $userId, $receiverId]);
        jsonResponse(['id' => $id], 201);
    }

    if (preg_match('#^/friendships/([a-f0-9-]+)$#i', $path, $matches)) {
        if ($method === 'PATCH') {
            $status = (string) (requestBody()['status'] ?? '');
            if (!in_array($status, ['accepted', 'declined'], true)) jsonResponse(['message' => 'Некорректный статус'], 400);
            $statement = execute('UPDATE friendships SET status = ? WHERE id = ? AND receiver_id = ?', [$status, $matches[1], $userId]);
            if ($statement->rowCount() === 0) jsonResponse(['message' => 'Нельзя изменить эту заявку'], 403);
            jsonResponse(['success' => true]);
        }
        if ($method === 'DELETE') {
            execute('DELETE FROM friendships WHERE id = ? AND (sender_id = ? OR receiver_id = ?)', [$matches[1], $userId, $userId]);
            jsonResponse(['success' => true]);
        }
    }

    jsonResponse(['message' => 'Маршрут не найден'], 404);
} catch (Throwable $error) {
    error_log((string) $error);
    jsonResponse(['message' => 'Внутренняя ошибка сервера'], 500);
}
