<?php


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require(dirname(__FILE__) . '/../vendor/autoload.php');

$sourceQueue = 'download';
$destinationQueue = 'upload';

$dir_save = '/app/data/downloads/';
$path_file_cookies = '/app/data/cookies/cookies.txt';

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
    function ($msg) use ($destinationQueue, $dir_save, $path_file_cookies) {
        $body = $msg->body;
        $body = json_decode($body, true);
        $video_id = $body["video_id"];

        $command = "yt-dlp -f bv+ba/b -o {$dir_save}{$video_id} -N 1 -R 5 --cookies {$path_file_cookies} https://www.youtube.com/watch?v={$video_id}";

        $process = proc_open($command, [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ], $pipes);

        if (is_resource($process)) {
            // Чтение вывода процесса
            while ($line = fgets($pipes[1])) {
                echo "STDOUT: $line";
            }

            // Чтение ошибок процесса
            while ($line = fgets($pipes[2])) {
                echo "STDERR: $line";
            }

            // Закрытие дескрипторов и ожидание завершения
            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);
            echo "Process exited with code: $returnCode";
            if ($returnCode === 0) {
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

                $msg_resp = new AMQPMessage(json_encode($body, JSON_UNESCAPED_UNICODE), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

                //Отправляем сообщение
                $msg->delivery_info['channel']->basic_publish($msg_resp, '', $destinationQueue);
            } else {
                sleep(3600);
                //todo выслать уведомление с ошибкой загрузки
            }
        } else {
            echo "Failed to start the process.";
        }
    });
while (count($channel->callbacks)) {
    $channel->wait();
}

