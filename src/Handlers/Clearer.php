<?php

namespace App\Handlers;


class Clearer implements IWorker
{

    public function work(array $task): array
    {
        $dir_save = '/app/data/downloads/';

        $files = glob($dir_save . $task['video_id'] . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file); // Удаляем файл
                echo "Удален: $file\n";
            }
        }

        return [null, null];
    }

    public function getPriority(): int
    {
        return 50;
    }
}
