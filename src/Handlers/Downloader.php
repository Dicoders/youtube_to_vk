<?php

namespace App\Handlers;

use App\Config;
use App\Task;
use App\YoutubeChannels;
use Exception;

class Downloader implements IWorker
{
    public function __construct(YoutubeChannels $channels)
    {
    }

    public function work(Task $task): array
    {
        $command = $this->buildCommand($task->video_id);

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new Exception('Failed to start the process.');
        }

        $isPrivateVideo = false;

        while (($line = fgets($pipes[1])) !== false) {
            echo "STDOUT: $line";
        }

        while (($line = fgets($pipes[2])) !== false) {
            echo "STDERR: $line";
            if (str_contains($line, 'Video unavailable. This video is private')) {
                $isPrivateVideo = true;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);
        echo "Process exited with code: $returnCode";

        if ($isPrivateVideo) {
            return [null, null];
        }

        if ($returnCode !== 0) {
            throw new Exception("Error download video, code: $returnCode");
        }

        return [Uploader::class, $task];
    }

    public static function getPriority(): int
    {
        return 10;
    }

    private function buildCommand(string $videoId): string
    {
        return sprintf(
            'yt-dlp -f bv[language^=ru]+ba[language^=ru]/bv+ba[language^=ru]/bv+ba -o "%s%s.%%(ext)s" -N 1 -R 5 --cookies %s https://www.youtube.com/watch?v=%s',
            Config::DIR_DOWNLOADS,
            $videoId,
            Config::PATH_COOKIES,
            $videoId
        );
    }
}