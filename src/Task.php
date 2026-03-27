<?php

namespace App;

class Task
{
    public function __construct(
        public readonly int    $channel_id,
        public readonly string $video_id,
        public readonly string $title,
        public readonly string $description,
        public readonly int    $vk_video_id = 0,
        public readonly int    $vk_post_id  = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            channel_id:  (int)($data['channel_id']   ?? 0),
            video_id:    (string)($data['video_id']   ?? ''),
            title:       (string)($data['title']      ?? ''),
            description: (string)($data['description'] ?? ''),
            vk_video_id: (int)($data['vk_video_id']  ?? 0),
            vk_post_id:  (int)($data['vk_post_id']   ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'channel_id'  => $this->channel_id,
            'video_id'    => $this->video_id,
            'title'       => $this->title,
            'description' => $this->description,
            'vk_video_id' => $this->vk_video_id,
            'vk_post_id'  => $this->vk_post_id,
        ];
    }

    public function withVkVideoId(int $vk_video_id): self
    {
        return new self(
            channel_id:  $this->channel_id,
            video_id:    $this->video_id,
            title:       $this->title,
            description: $this->description,
            vk_video_id: $vk_video_id,
            vk_post_id:  $this->vk_post_id,
        );
    }

    public function withVkPostId(int $vk_post_id): self
    {
        return new self(
            channel_id:  $this->channel_id,
            video_id:    $this->video_id,
            title:       $this->title,
            description: $this->description,
            vk_video_id: $this->vk_video_id,
            vk_post_id:  $vk_post_id,
        );
    }
}