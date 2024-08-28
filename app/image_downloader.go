package main

import (
    "fmt"
    "os"
    "path/filepath"
    "net/http"
	"image"
    "app/share"

	"image/jpeg"

	_ "image/jpeg" // Поддержка формата JPEG
)

const downloadFolder = "./images"
const queue_source = "image_download"
const queue_destination = "clear"

func init() {
    // Create download directory if it doesn't exist
    if _, err := os.Stat(downloadFolder); os.IsNotExist(err) {
        os.Mkdir(downloadFolder, os.ModePerm)
    }

    //Объявление используемых очередей
    share.DeclarateQueue(queue_source)
    share.DeclarateQueue(queue_destination)
}

func main() {
    share.RabbitConnect(queue_source, queue_destination, processMessage)
    share.CloseConnection()
}

func processMessage(video share.Video) (share.Video, error) {
    fileURL := "https://i3.ytimg.com/vi/"+video.VideoID+"/hqdefault.jpg"

    // Выполняем HTTP-запрос для получения файла
    response, err := http.Get(fileURL)
    if err != nil {
        fmt.Println("Ошибка при загрузке файла:", err)
        return video, err
    }
    defer response.Body.Close()

    // Проверяем, что запрос был успешным
    if response.StatusCode != http.StatusOK {
        fmt.Printf("Ошибка: статус-код %d при загрузке файла\n", response.StatusCode)
        return video, err
    }

    // Декодируем изображение
    img, _, err := image.Decode(response.Body)
    if err != nil {
        fmt.Println("Ошибка декодирования изображения:", err)
        return video, err
    }

    // Указываем координаты прямоугольника для обрезки
    x0, y0 := 0, 45
    x1, y1 := 480, 315
    rect := image.Rect(x0, y0, x1, y1)

	// Выполняем обрезку
	croppedImg := img.(interface {
		SubImage(r image.Rectangle) image.Image
	}).SubImage(rect)

    // Создаём файл для записи
    file, err := os.Create(filepath.Join(downloadFolder, fmt.Sprintf("%d.jpg", video.VkVideoID)))
    if err != nil {
        fmt.Println("Ошибка при создании изображения:", err)
        return video, err
    }
    defer file.Close()

    // Копируем содержимое ответа в файл
    err = jpeg.Encode(file, croppedImg, nil)
    if err != nil {
        fmt.Println("Ошибка сохранения изображения:", err)
        return video, err
    }

    fmt.Println("Изображение успешно сохранено!")

    return video, nil
}

