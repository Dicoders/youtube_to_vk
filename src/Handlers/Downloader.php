<?php

namespace App\Handlers;

use Exception;

class Downloader implements IWorker
{
    public function work(array $task): array
    {
        $dir_save = '/app/data/downloads/';
        $path_file_cookies = '/app/data/cookies/cookies.txt';

        $video_id = $task["video_id"];

        $command = "yt-dlp -f bv+ba/b -o {$dir_save}{$video_id} -N 1 -R 5 --cookies {$path_file_cookies} https://www.youtube.com/watch?v={$video_id}";

        $process = proc_open($command, [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ], $pipes);

        if (is_resource($process)) {
            // Чтение вывода процесса
            while ($line = fgets($pipes[1])) {
                echo "STDOUT: $line";
            }

            // Чтение ошибок процесса
            while ($line = fgets($pipes[2])) {
                echo "STDERR: $line";
                if (str_contains($line, 'Video unavailable. This video is private')) {
                    return [];
                }
            }

            // Закрытие дескрипторов и ожидание завершения
            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);
            echo "Process exited with code: $returnCode";
            if ($returnCode === 0) {
                return [Uploader::class, $task];
            } else {
                throw new Exception("Error download video, code: $returnCode");
            }
        } else {
            throw new Exception("Failed to start the process.");
        }
    }

    public function getPriority(): int
    {
        return 10;
    }
}

