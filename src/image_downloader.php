<?php


use GuzzleHttp\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require(dirname(__FILE__) . '/../vendor/autoload.php');

$sourceQueue = 'image_download';
$destinationQueue = 'clear';
$path_images = '/app/data/images/';

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
    function ($msg) use ($destinationQueue, $path_images) {
        $body = $msg->body;
        $body = json_decode($body, true);

        $path = '/tmp/' . $body['video_id'] . '.jpg';

        $client = new Client();
        $response = $client->get("https://i3.ytimg.com/vi/{$body['video_id']}/hqdefault.jpg", [
            'sink' => $path
        ]);

        if ($response->getStatusCode() !== 200) {
            echo 'Ошибка скачивания изображения';
            sleep(3600);
            return;
            //todo отправить сообщение об ошибке
        }

        $pathDest = $path_images . $body['vk_video_id'] . '.jpg';
        try {
            cropImage($path, $pathDest, 0, 45, 480, 270);
        } catch (\Throwable $th) {
            echo $th->getMessage();
            sleep(3600);
            //todo отправить сообщение об ошибке
        }

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

        $msg_resp = new AMQPMessage(json_encode($body, JSON_UNESCAPED_UNICODE), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        //Отправляем сообщение
        $msg->delivery_info['channel']->basic_publish($msg_resp, '', $destinationQueue);

    });
while (count($channel->callbacks)) {
    $channel->wait();
}

function cropImage($sourcePath, $destPath, $x, $y, $width, $height) {
    // Загружаем исходное изображение
    $srcImage = imagecreatefromjpeg($sourcePath);
    if (!$srcImage) {
        die("Не удалось загрузить изображение.");
    }

    // Создаем пустое изображение для результата
    $croppedImage = imagecreatetruecolor($width, $height);

    // Обрезаем
    if (!imagecopyresampled(
        $croppedImage, $srcImage,
        0, 0, $x, $y, $width, $height, $width, $height
    )) {
        die("Ошибка при обрезке изображения.");
    }

    // Сохраняем результат
    if (!imagejpeg($croppedImage, $destPath)) {
        die("Ошибка при сохранении изображения.");
    }

    // Освобождаем память
    imagedestroy($srcImage);
    imagedestroy($croppedImage);

    echo "Изображение обрезано и сохранено в $destPath";
}
