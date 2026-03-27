<?php

namespace App\Handlers;

use App\Task;
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

    public function work(Task $task): array
    {
        $channel = $this->channels->getChannelById($task->channel_id);

        $client = new Client([
            'base_uri' => 'https://api.vk.ru',
            'headers'  => [
                'Authorization' => 'Bearer ' . $channel['vk_access_user_token'],
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
        ]);

        $response = $client->post('/method/wall.post', [
            'form_params' => [
                'v'           => '5.199',
                'owner_id'    => $channel['vk_owner_id'],
                'from_group'  => 1,
                'attachments' => sprintf('video%s_%d', $channel['vk_owner_id'], $task->vk_video_id),
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Ошибка создания поста. статус ' . $response->getStatusCode());
        }

        $content    = json_decode($response->getBody()->getContents(), true);
        $vk_post_id = $content['response']['post_id'];

        return [ImageDownloader::class, $task->withVkPostId($vk_post_id)];
    }

    public static function getPriority(): int
    {
        return 30;
    }
}