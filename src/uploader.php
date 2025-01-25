<?php


use GuzzleHttp\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require(dirname(__FILE__) . '/../vendor/autoload.php');

$sourceQueue = 'upload';
$destinationQueue = 'wall_post';
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

        $client = new Client([
            'base_uri' => 'https://api.vk.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['VK_ACCESS_TOKEN'],
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $response = $client->post('/method/video.save', [
            'form_params' => [
                'v' => '5.119',
                'name' => $body['title'],
                'description' => $body['description'],
                'group_id' => $_ENV['VK_GROUP_ID'],
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            sleep(3600);
            return;
            //todo отправить сообщение об ошибке
        }

        $content = $response->getBody()->getContents();
        $content = json_decode($content, true);

        $vk_video_id = $content['response']['video_id'];
        $upload_url = $content['response']['upload_url'];

        $path_file = null;
        $file_name = null;
        $formats = ['mp4', 'mov', 'avi', 'wmv', 'flv', '3gp', 'webm', 'mkv'];

        foreach ($formats as $format) {
            $file_name = $body['video_id'] . '.' . $format;
            $path = $dir_save . $file_name;
            $exist = file_exists($path);
            if ($exist) {
                $path_file = $path;
                break;
            }
        }

        if (is_null($path_file)) {
            sleep(3600);
            return;
            //todo отправить сообщение об ошибке
        }

        $lastPercent = 0.0;
        $client2 = new Client();
        $response = $client2->post($upload_url, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($path_file, 'r'),
                    'filename' => $file_name
                ],
                [
                    'name' => 'key',
                    'contents' => 'value',
                ],
            ],
            'progress' => function ($downloadTotal, $downloaded, $uploadTotal, $uploaded) use (&$lastPercent) {
                if ($uploadTotal > 0) {
                    $percent = round(($uploaded / $uploadTotal) * 100, 1);
                    if ($percent !== $lastPercent) {
                        echo "Uploaded: $percent%\r";
                    }
                }
            },
        ]);

        if (!in_array($response->getStatusCode(), [200, 201])) {
            sleep(3600);
            //todo отправить сообщение об ошибке
        }

        $body['vk_video_id'] = $vk_video_id;

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

        $msg_resp = new AMQPMessage(json_encode($body, JSON_UNESCAPED_UNICODE), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        //Отправляем сообщение
        $msg->delivery_info['channel']->basic_publish($msg_resp, '', $destinationQueue);

    });
while (count($channel->callbacks)) {
    $channel->wait();
}

