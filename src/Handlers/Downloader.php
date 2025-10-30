<?php

namespace App\Handlers;

use App\YoutubeChannels;
use Exception;

class Downloader implements IWorker
{
    private const DIR_SAVE = '/app/data/downloads/';
    private const PATH_FILE_COOKIES = '/app/data/cookies/cookies.txt';

    public function __construct(YoutubeChannels $channels)
    {
    }

    /**
     * Выполняет задачу скачивания видео по video_id.
     *
     * @param array $task
     * @return array
     * @throws Exception
     */
    public function work(array $task): array
    {
        $videoId = $task['video_id'];
        $command = $this->buildCommand($videoId);

        $process = proc_open($command, [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ], $pipes);

        if (!is_resource($process)) {
            throw new Exception('Failed to start the process.');
        }

        $isPrivateVideo = false;

        // Чтение вывода процесса
        while (($line = fgets($pipes[1])) !== false) {
            echo "STDOUT: $line";
        }

        // Чтение ошибок процесса и проверка на приватное видео
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
            return [];
        }

        if ($returnCode !== 0) {
            throw new Exception("Error download video, code: $returnCode");
        }

        return [Uploader::class, $task];
    }

    /**
     * Возвращает приоритет задачи.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Формирует команду для скачивания видео. И
     *
     * @param string $videoId
     * @return string
     */
    private function buildCommand(string $videoId): string
    {
        return sprintf(
            'yt-dlp -f 301-5/300-5/94-5/401+251/315+251/400+251/308+251 -o %s%s -N 1 -R 5 --cookies %s https://www.youtube.com/watch?v=%s',
            self::DIR_SAVE,
            $videoId,
            self::PATH_FILE_COOKIES,
            $videoId
        );
    }
}

