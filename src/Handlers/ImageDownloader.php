<?php

namespace App\Handlers;

use Exception;
use GuzzleHttp\Client;

class ImageDownloader implements IWorker
{

    public function work(array $task): array
    {
        $path_images = '/app/data/images/';

        $path = '/tmp/' . $task['video_id'] . '.jpg';
        $remote_image_path = "https://i3.ytimg.com/vi/{$task['video_id']}/hqdefault.jpg";

        $client = new Client();
        $response = $client->get($remote_image_path, [
            'sink' => $path
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Ошибка скачивания изображения');
        }

        $pathDest = $path_images . $task['vk_video_id'] . '.jpg';

        $this->cropImage($path, $pathDest, 0, 45, 480, 270);

        return [ImageUploader::class, $task];
    }

    private function cropImage($sourcePath, $destPath, $x, $y, $width, $height)
    {
        // Загружаем исходное изображение
        $srcImage = imagecreatefromjpeg($sourcePath);
        if (!$srcImage) {
            throw new Exception("Не удалось загрузить изображение.");
        }

        // Создаем пустое изображение для результата
        $croppedImage = imagecreatetruecolor($width, $height);

        // Обрезаем
        if (!imagecopyresampled(
            $croppedImage, $srcImage,
            0, 0, $x, $y, $width, $height, $width, $height
        )) {
            throw new Exception("Ошибка при обрезке изображения.");
        }

        // Сохраняем результат
        if (!imagejpeg($croppedImage, $destPath)) {
            throw new Exception("Ошибка при сохранении изображения.");
        }

        // Освобождаем память
        imagedestroy($srcImage);
        imagedestroy($croppedImage);

        echo "Изображение обрезано и сохранено в $destPath \n";
    }

    public function getPriority(): int
    {
        return 40;
    }
}
