<?php

namespace App;

class YoutubeChannels
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getChannelById(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM youtube_channels WHERE id = :id");
        $stmt->execute([
            'id' => $id,
        ]);
        return $stmt->fetch();
    }

    public function getChannels(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM youtube_channels");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
