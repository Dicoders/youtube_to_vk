<?php

namespace App\Handlers;

use Exception;
use GuzzleHttp\Client;

class ImageUploader implements IWorker
{

    public function work(array $task): array
    {
        $path_image = '/app/data/images/' . $task['vk_video_id'] . '.jpg';

        if (file_exists($path_image)) {

            $client = new Client([
                'base_uri' => 'https://api.vk.ru',
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);
            $response = $client->post('/method/video.getThumbUploadUrl', [
                'form_params' => [
                    'v' => '5.199',
                    'access_token' => $_ENV['VK_ACCESS_USER_TOKEN'],
                    'owner_id' => $_ENV['VK_OWNER_ID'],
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Ошибка получения ссылки для загрузки изображения');
            }

            $content = $response->getBody()->getContents();
            $content = json_decode($content, true);
            if (isset($content['error'])) {
                throw new Exception($content['error']['error_msg']);
            }

            $upload_url = $content['response']['upload_url'];

            $client2 = new Client();
            $response = $client2->post($upload_url, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($path_image, 'r'),
                        'filename' => $task['vk_video_id'] . '.jpg'
                    ],
                    [
                        'name' => 'key',
                        'contents' => 'value',
                    ],
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Ошибка загрузки изображения');
            }

            $json_data = $response->getBody()->getContents();

            $response = $client->post('/method/video.saveUploadedThumb', [
                'form_params' => [
                    'v' => '5.199',
                    'access_token' => $_ENV['VK_ACCESS_USER_TOKEN'],
                    'owner_id' => $_ENV['VK_OWNER_ID'],
                    'thumb_json' => $json_data,
                    'video_id' => $task['vk_video_id'],
                    'set_thumb' => 1
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Ошибка установки изображения для видео');
            }
            $content = $response->getBody()->getContents();
            $content = json_decode($content, true);
            if (isset($content['error'])) {
                throw new Exception($content['error']['error_msg']);
            }

        }
        return [Clearer::class, $task];
    }


    public function getPriority(): int
    {
        return 45;
    }
}
