<?php

namespace App\Handlers;

use App\Config;
use App\Task;
use App\YoutubeChannels;
use Exception;
use GuzzleHttp\Client;

class ImageDownloader implements IWorker
{
    public function __construct(YoutubeChannels $channels)
    {
    }

    public function work(Task $task): array
    {
        $path              = '/tmp/' . $task->video_id . '.jpg';
        $remote_image_path = "https://i3.ytimg.com/vi/{$task->video_id}/hqdefault.jpg";

        $client   = new Client();
        $response = $client->get($remote_image_path, ['sink' => $path]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Ошибка скачивания изображения');
        }

        $pathDest = Config::DIR_IMAGES . $task->vk_video_id . '.jpg';
        $this->cropImage($path, $pathDest, 0, 45, 480, 270);

        return [ImageUploader::class, $task];
    }

    private function cropImage(string $sourcePath, string $destPath, int $x, int $y, int $width, int $height): void
    {
        $srcImage = imagecreatefromjpeg($sourcePath);
        if (!$srcImage) {
            throw new Exception("Не удалось загрузить изображение.");
        }

        $croppedImage = imagecreatetruecolor($width, $height);

        if (!imagecopyresampled($croppedImage, $srcImage, 0, 0, $x, $y, $width, $height, $width, $height)) {
            throw new Exception("Ошибка при обрезке изображения.");
        }

        if (!imagejpeg($croppedImage, $destPath)) {
            throw new Exception("Ошибка при сохранении изображения.");
        }

        imagedestroy($srcImage);
        imagedestroy($croppedImage);

        echo "Изображение обрезано и сохранено в $destPath \n";
    }

    public static function getPriority(): int
    {
        return 40;
    }
}