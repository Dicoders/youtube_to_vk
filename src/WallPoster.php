<?php

use GuzzleHttp\Client;



class WallPoster implements IWorker
{

    public function work(array $task): array
    {
        $client = new Client([
            'base_uri' => 'https://api.vk.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['VK_ACCESS_USER_TOKEN'],
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $response = $client->post('/method/wall.post', [
            'form_params' => [
                'v' => '5.199',
                'owner_id' => $_ENV['VK_OWNER_ID'],
                'from_group' => 1,
                'attachments' => sprintf('video%s_%d', $_ENV['VK_OWNER_ID'], $task['vk_video_id']),
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Ошибка создания поста. статус ' . $response->getStatusCode());
        }

        $content = $response->getBody()->getContents();
        $content = json_decode($content, true);

        $vk_post_id = $content['response']['post_id'];
        $task['vk_post_id'] = $vk_post_id;

        return [ImageDownloader::class, $task];
    }

    public function getPriority(): int
    {
        return 20;
    }
}

