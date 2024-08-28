package main

import (
    "fmt"
    "log"
    "os"
    "path/filepath"
    "app/share"
)

const downloadFolder = "./downloads"
const queue_source = "clear"
const queue_destination = "completed"

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
    files, err := filepath.Glob(filepath.Join(downloadFolder, fmt.Sprintf("%s.*", video.VideoID)))
    if err != nil {
        log.Printf("Отсутствует файл %s", video.VideoID)
    }
    for _, file := range files {
        err = os.Remove(file)
        if err != nil {
            log.Printf("Ошибка удаления файла %s: %v", file, err)
        }
    }
    return video, nil
}

