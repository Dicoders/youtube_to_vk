<?php

namespace App\Handlers;
use App\YoutubeChannels;

interface IWorker
{
    public function __construct(YoutubeChannels $channels);

    public function work(array $task): array;

    public static function getPriority(): int;
}
