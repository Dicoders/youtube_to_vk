<?php


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require(dirname(__FILE__) . '/../vendor/autoload.php');


$destinationQueue = 'download';
$database = '/app/data/db/videos.db';

$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_DEFAULT_USER'],
    $_ENV['RABBITMQ_DEFAULT_PASS'],
    $_ENV['RABBITMQ_DEFAULT_VHOST'],
);

$channel = $connection->channel();
$channel->queue_declare($destinationQueue, false, true, false, false);
$channel->basic_qos(null, 1, null);



$db = new SQLite3($database);

//Создаем базу если её нет
$db->exec('CREATE TABLE IF NOT EXISTS videos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        video_id TEXT NOT NULL UNIQUE,
        title TEXT,
        description TEXT,
        vk_video_id INTEGER,
        vk_post_id INTEGER
    );');



$url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $_ENV['YOUTUBE_CHANNEL_ID'];

$xml = simplexml_load_file($url);

foreach ($xml->entry as $item) {
    $id = str_replace('yt:video:', '', (string)$item->id[0]);
    $title = $item->title . ' 4k 60fps REUP';
    $description = $item->children('http://search.yahoo.com/mrss/')->group->description[0];

    $query = $db->prepare('select id from videos where video_id=:video_id');
    $query->bindValue(':video_id', $id);
    $result = $query->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if (empty($row)) {
        $query = $db->prepare('insert into videos (video_id,title,description) values (:video_id,:title,:description)');
        $query->bindValue(':video_id', $id);
        $query->bindValue(':title', $title);
        $query->bindValue(':description', $description);
        $query->execute();

        $body = [
            'video_id' => $id,
            'title' => $title,
            'description' => $description,
            'vk_video_id' => 0,
            'vk_post_id' => 0,
        ];

        $msg_resp = new AMQPMessage(json_encode($body, JSON_UNESCAPED_UNICODE), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        //Отправляем сообщение
        $channel->basic_publish($msg_resp, '', $destinationQueue);

    }
}


$db->close();
