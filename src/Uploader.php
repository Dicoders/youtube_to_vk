<?php

use GuzzleHttp\Client;

class Uploader implements IWorker
{

    public function work(array $task): array
    {
        $dir_save = '/app/data/downloads/';

        $client = new Client([
            'base_uri' => 'https://api.vk.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['VK_ACCESS_TOKEN'],
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $response = $client->post('/method/video.save', [
            'form_params' => [
                'v' => '5.199',
                'name' => $task['title'],
                'description' => $task['description'],
                'group_id' => $_ENV['VK_GROUP_ID'],
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Ошибка получения ссылки для загрузки видео');
        }

        $content = $response->getBody()->getContents();
        $content = json_decode($content, true);

        if (isset($content['error'])) {
            throw new Exception($content['error']['error_msg']);
        }

        $vk_video_id = $content['response']['video_id'];
        $upload_url = $content['response']['upload_url'];

        $path_file = null;
        $file_name = null;
        $formats = ['mp4', 'mov', 'avi', 'wmv', 'flv', '3gp', 'webm', 'mkv'];

        foreach ($formats as $format) {
            $file_name = $task['video_id'] . '.' . $format;
            $path = $dir_save . $file_name;
            $exist = file_exists($path);
            if ($exist) {
                $path_file = $path;
                break;
            }
        }

        if (is_null($path_file)) {
            return []; //не найдено видео для загрузки в ВК
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
            throw new Exception('Ошибка загрузки видео в ВК. Статус ' . $response->getStatusCode());
        }

        $task['vk_video_id'] = $vk_video_id;

        //Отправляем сообщение
        return [WallPoster::class, $task];
    }

    public function getPriority(): int
    {
        return 10;
    }
}

