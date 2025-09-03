<?php

require __DIR__ . '/GoCache/Cache.php';
use GoCache\Cache;

define('DB_MESSAGES', __DIR__ . '/messages.json');
define('DB_USERS', [
    1 => ['id' => 1, 'name' => 'Alice'],
    2 => ['id' => 2, 'name' => 'Bob'],
]);

function get_user_profile(int $userId): array
{
    $key = 'user_profile:' . $userId;

    return Cache::remember($key, 3600, function () use ($userId) {
        sleep(1);
        error_log("DATABASE HIT for user ID: $userId"); // Pour voir dans la console du serveur
        return DB_USERS[$userId] ?? ['id' => $userId, 'name' => 'Unknown'];
    });
}


header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_messages':
        $messages = file_exists(DB_MESSAGES) ? json_decode(file_get_contents(DB_MESSAGES), true) : [];
        $enrichedMessages = [];
        foreach ($messages as $message) {
            $message['user'] = get_user_profile($message['userId']);
            $enrichedMessages[] = $message;
        }
        echo json_encode(array_reverse($enrichedMessages));
        break;

    case 'post_message':
        $input = json_decode(file_get_contents('php://input'), true);
        $messages = file_exists(DB_MESSAGES) ? json_decode(file_get_contents(DB_MESSAGES), true) : [];
        
        $messages[] = [
            'userId' => (int)($input['userId'] ?? 1),
            'text' => htmlspecialchars($input['text'] ?? ''),
            'time' => time(),
        ];
        
        file_put_contents(DB_MESSAGES, json_encode($messages));
        echo json_encode(['status' => 'ok']);
        break;
}