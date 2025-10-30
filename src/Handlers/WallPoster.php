<?php

namespace App\Handlers;

use App\YoutubeChannels;
use Exception;
use GuzzleHttp\Client;


class WallPoster implements IWorker
{
    private YoutubeChannels $channels;

    public function __construct(YoutubeChannels $channels)
    {
        $this->channels = $channels;
    }

    public function work(array $task): array
    {
        $channel = $this->channels->getChannelById($task['channel_id']);

        $client = new Client([
            'base_uri' => 'https://api.vk.ru',
            'headers' => [
                'Authorization' => 'Bearer ' . $channel['vk_access_user_token'],
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $response = $client->post('/method/wall.post', [
            'form_params' => [
                'v' => '5.199',
                'owner_id' => $channel['vk_owner_id'],
                'from_group' => 1,
                'attachments' => sprintf('video%s_%d', $channel['vk_owner_id'], $task['vk_video_id']),
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

    public static function getPriority(): int
    {
        return 30;
    }
}

