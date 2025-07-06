<?php

set_time_limit(0);

require(dirname(__FILE__) . '/../vendor/autoload.php');
$pdo = new PDO('sqlite:' . 'data/db/videos.db');

$queue = new Queue($pdo);

if (empty($queue->size())) {
    echo "No jobs.\n";
    exit;
}

// Пытаемся заблокировать
if (!$queue->acquireLock()) {
    echo "Another worker is active. Exiting.\n";
    exit;
}


try {
    [$task_id, $class_handler, $data] = $queue->pop();

    /** @var IWorker $class_handler */
    $class = new $class_handler();
    [$next_work, $data] = $class->work($data);

    if ($next_work) {
        $queue->push($next_work, $data);
    }

} catch (Throwable $t) {
    $queue->rollback($task_id);
    echo $t->getMessage() . "\n";
} finally {
    $queue->releaseLock();
}


