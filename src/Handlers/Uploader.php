<?php

namespace App\Handlers;

use App\Config;
use App\Task;
use App\YoutubeChannels;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

class Uploader implements IWorker
{
    private YoutubeChannels $channels;

    public function __construct(YoutubeChannels $channels)
    {
        $this->channels = $channels;
    }

    public function work(Task $task): array
    {
        $channel = $this->channels->getChannelById($task->channel_id);

        $client = new Client([
            'base_uri' => 'https://api.vk.ru',
            'headers'  => [
                'Authorization' => 'Bearer ' . $channel['vk_access_group_token'],
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
        ]);

        $response = $client->post('/method/video.save', [
            'form_params' => [
                'v'           => '5.199',
                'name'        => $task->title,
                'description' => $task->description,
                'group_id'    => $channel['vk_group_id'],
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Ошибка получения ссылки для загрузки видео');
        }

        $content = json_decode($response->getBody()->getContents(), true);
        if (isset($content['error'])) {
            throw new Exception($content['error']['error_msg']);
        }

        $vk_video_id = $content['response']['video_id'];
        $upload_url  = $content['response']['upload_url'];

        $path_file = null;
        $file_name = null;
        foreach (['mp4', 'mov', 'avi', 'wmv', 'flv', '3gp', 'webm', 'mkv'] as $format) {
            $file_name = $task->video_id . '.' . $format;
            $path = Config::DIR_DOWNLOADS . $file_name;
            if (file_exists($path)) {
                $path_file = $path;
                break;
            }
        }

        if (is_null($path_file)) {
            return [null, null];
        }

        $client2      = new Client();
        $fileSize     = filesize($path_file);
        $lastUploaded = 0;
        $lastPercent  = 0.0;
        $response     = null;

        for ($attempt = 1; $attempt <= Config::RETRIES_UPLOAD; $attempt++) {
            $offset = $lastUploaded;
            $handle = fopen($path_file, 'r');
            if ($offset > 0) {
                fseek($handle, $offset);
            }

            $options = [
                'multipart' => [
                    ['name' => 'file', 'contents' => $handle, 'filename' => $file_name],
                    ['name' => 'key',  'contents' => 'value'],
                ],
                'progress' => function ($downloadTotal, $downloaded, $uploadTotal, $uploaded) use (&$lastPercent, &$lastUploaded, $offset) {
                    if ($uploadTotal > 0) {
                        $lastUploaded = $offset + $uploaded;
                        $percent = round(($lastUploaded / ($uploadTotal + $offset)) * 100, 1);
                        if ($percent !== $lastPercent) {
                            $lastPercent = $percent;
                            echo "Uploaded: $percent%\n";
                        }
                    }
                },
            ];

            if ($lastUploaded > 0) {
                $options['headers'] = [
                    'Content-Range' => "bytes $lastUploaded-" . ($fileSize - 1) . "/$fileSize",
                ];
            }

            try {
                $response = $client2->post($upload_url, $options);
                break;
            } catch (TransferException $e) {
                fclose($handle);
                if ($attempt === Config::RETRIES_UPLOAD) {
                    throw $e;
                }
                $percent = $fileSize > 0 ? round(($lastUploaded / $fileSize) * 100, 1) : 0;
                echo "Попытка $attempt не удалась, дозагрузка с $percent%\n";
            }
        }

        if (!in_array($response->getStatusCode(), [200, 201])) {
            throw new Exception('Ошибка загрузки видео в ВК. Статус ' . $response->getStatusCode());
        }

        return [WallPoster::class, $task->withVkVideoId($vk_video_id)];
    }

    public static function getPriority(): int
    {
        return 20;
    }
}
