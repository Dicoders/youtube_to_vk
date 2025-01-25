<?php


use GuzzleHttp\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require(dirname(__FILE__) . '/../vendor/autoload.php');

$sourceQueue = 'clear';
$destinationQueue = 'completed';

$dir_save = '/app/data/downloads/';

$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_DEFAULT_USER'],
    $_ENV['RABBITMQ_DEFAULT_PASS'],
    $_ENV['RABBITMQ_DEFAULT_VHOST'],
);

$channel = $connection->channel();
$channel->queue_declare($sourceQueue, false, true, false, false);
$channel->queue_declare($destinationQueue, false, true, false, false);
$channel->basic_qos(null, 1, null);
$channel->basic_consume($sourceQueue, '', false, false, false, false,
    function ($msg) use ($destinationQueue, $dir_save) {
        $body = $msg->body;
        $body = json_decode($body, true);

        $files = glob($dir_save . $body['video_id'] . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file); // Удаляем файл
                echo "Удален: $file\n";
            }
        }

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

        $msg_resp = new AMQPMessage(json_encode($body, JSON_UNESCAPED_UNICODE), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        //Отправляем сообщение
        $msg->delivery_info['channel']->basic_publish($msg_resp, '', $destinationQueue);

    });
while (count($channel->callbacks)) {
    $channel->wait();
}
