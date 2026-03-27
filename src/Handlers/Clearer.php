<?php

namespace App\Handlers;

use App\Config;
use App\Task;
use App\YoutubeChannels;

class Clearer implements IWorker
{
    public function __construct(YoutubeChannels $channels)
    {
    }

    public function work(Task $task): array
    {
        $files = glob(Config::DIR_DOWNLOADS . $task->video_id . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                echo "Удален: $file\n";
            }
        }

        return [null, null];
    }

    public static function getPriority(): int
    {
        return 50;
    }
}