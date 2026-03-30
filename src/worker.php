<?php

use App\Handlers\IWorker;
use App\Queue;
use App\YoutubeChannels;
use GuzzleHttp\Client;

set_time_limit(0);

require(dirname(__FILE__) . '/../vendor/autoload.php');
$pdo = new PDO('sqlite:' . dirname(__FILE__) . '/../data/db/videos.db');

$queue = new Queue($pdo);
$youtubeChannels = new YoutubeChannels($pdo);

if (empty($queue->size())) {
    echo "No jobs.\n";
    exit;
}

if (!$queue->acquireLock()) {
    echo "Another worker is active. Exiting.\n";
    exit;
}

try {
    [$task_id, $class_handler, $task] = $queue->pop();

    /** @var IWorker $instance */
    $instance = new $class_handler($youtubeChannels);
    [$next_work, $next_task] = $instance->work($task);

    if ($next_work) {
        $queue->push($next_work, $next_task);
    }

} catch (Throwable $t) {
    $queue->rollback($task_id);

    $isFinal = $queue->isFailed($task_id);
    $prefix = $isFinal
        ? 'YoutubeToVK ОКОНЧАТЕЛЬНАЯ ОШИБКА (исчерпаны попытки)'
        : 'YoutubeToVK ошибка (retry)';

    echo $t->getMessage() . "\n";
    if ($isFinal) {
        $client = new Client(['base_uri' => 'https://api.telegram.org']);
        $client->post('/bot' . $_ENV['TG_BOT_TOKEN'] . '/sendMessage', [
            'form_params' => [
                'chat_id' => $_ENV['TG_CHAT_ID'],
                'text' => $prefix . ': ' . $t->getMessage(),
                'parse_mode' => '',
            ],
        ]);
    }
} finally {
    $queue->releaseLock();
}
