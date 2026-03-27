<?php

namespace App\Handlers;

use App\Task;
use App\YoutubeChannels;

interface IWorker
{
    public function __construct(YoutubeChannels $channels);

    public function work(Task $task): array;

    public static function getPriority(): int;
}