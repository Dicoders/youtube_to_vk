<?php


use GuzzleHttp\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require(dirname(__FILE__) . '/../vendor/autoload.php');

$sourceQueue = 'wall_post';
$destinationQueue = 'image_download';

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
    function ($msg) use ($destinationQueue) {
        $body = $msg->body;
        $body = json_decode($body, true);

        $client = new Client([
            'base_uri' => 'https://api.vk.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['VK_ACCESS_TOKEN'],
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $response = $client->post('/method/wall.post', [
            'form_params' => [
                'v' => '5.119',
                'owner_id' => $_ENV['VK_OWNER_ID'],
                'from_group' => '1',
                'attachments' => sprintf('video%s_%d', $_ENV['VK_OWNER_ID'], $body['vk_video_id']),
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            sleep(3600);
            return;
            //todo отправить сообщение об ошибке
        }

        $content = $response->getBody()->getContents();
        $content = json_decode($content, true);

        $vk_post_id = $content['response']['post_id'];
        $body['vk_post_id'] = $vk_post_id;

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

        $msg_resp = new AMQPMessage(json_encode($body, JSON_UNESCAPED_UNICODE), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        //Отправляем сообщение
        $msg->delivery_info['channel']->basic_publish($msg_resp, '', $destinationQueue);

    });
while (count($channel->callbacks)) {
    $channel->wait();
}

