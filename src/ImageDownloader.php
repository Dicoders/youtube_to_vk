<?php

use GuzzleHttp\Client;

class ImageDownloader implements IWorker
{

    public function work(array $task): array
    {
        $path_images = '/app/data/images/';

        $path = '/tmp/' . $task['video_id'] . '.jpg';

        $client = new Client();
        $response = $client->get("https://i3.ytimg.com/vi/{$task['video_id']}/hqdefault.jpg", [
            'sink' => $path
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Ошибка скачивания изображения');
        }

        $pathDest = $path_images . $task['vk_video_id'] . '.jpg';
        try {
            $this->cropImage($path, $pathDest, 0, 45, 480, 270);
        } catch (\Throwable $th) {
            echo $th->getMessage();
            sleep(3600);
            //todo отправить сообщение об ошибке
        }

        return [Clearer::class, $task];
    }

    private function cropImage($sourcePath, $destPath, $x, $y, $width, $height)
    {
        // Загружаем исходное изображение
        $srcImage = imagecreatefromjpeg($sourcePath);
        if (!$srcImage) {
            die("Не удалось загрузить изображение.");
        }

        // Создаем пустое изображение для результата
        $croppedImage = imagecreatetruecolor($width, $height);

        // Обрезаем
        if (!imagecopyresampled(
            $croppedImage, $srcImage,
            0, 0, $x, $y, $width, $height, $width, $height
        )) {
            die("Ошибка при обрезке изображения.");
        }

        // Сохраняем результат
        if (!imagejpeg($croppedImage, $destPath)) {
            die("Ошибка при сохранении изображения.");
        }

        // Освобождаем память
        imagedestroy($srcImage);
        imagedestroy($croppedImage);

        echo "Изображение обрезано и сохранено в $destPath";
    }

    public function getPriority(): int
    {
        return 30;
    }
}
