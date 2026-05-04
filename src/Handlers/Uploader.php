<?php

namespace App\Handlers;

use App\Config;
use App\Task;
use App\YoutubeChannels;
use Exception;
use GuzzleHttp\Client;

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
                'privacy_view' => $channel['privacy_view'] ?? 'all',
                'donut_level_id' => $channel['donut_level_id'] ?? 0
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

        $lastPercent = 0.0;
        $client2 = new Client();
        $response = $client2->post($upload_url, [
            'multipart' => [
                ['name' => 'file', 'contents' => fopen($path_file, 'r'), 'filename' => $file_name],
                ['name' => 'key',  'contents' => 'value'],
            ],
            'progress' => function ($downloadTotal, $downloaded, $uploadTotal, $uploaded) use (&$lastPercent) {
                if ($uploadTotal > 0) {
                    $percent = round(($uploaded / $uploadTotal) * 100, 1);
                    if ($percent !== $lastPercent) {
                        $lastPercent = $percent;
                        echo "Uploaded: $percent%\r";
                    }
                }
            },
        ]);

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
