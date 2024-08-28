package main

import (
    "os"
    "fmt"
    "log"
    "path/filepath"
    "os/exec"
    "syscall"
    "os/signal"
    "app/share"
)

const downloadFolder = "./downloads"
const queue_source = "download"
const queue_destination = "upload"

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

// Пример функции обработки сообщения
func processMessage(video share.Video) (share.Video, error) {
    log.Printf("Received a video: %s %s", video.VideoID, video.Title)
    videoURL := fmt.Sprintf("https://www.youtube.com/watch?v=%s", video.VideoID)

    downloadPath := filepath.Join(downloadFolder, fmt.Sprintf("%s.%%(ext)s", video.VideoID))

    cmd := exec.Command("yt-dlp", "-f", "bv+ba/b" ,"-o", downloadPath, "-N 10", "-R 100", videoURL)
    cmd.SysProcAttr = &syscall.SysProcAttr{
        Setpgid: true, // Устанавливаем процесс в новую группу процессов
    }
    err := cmd.Start()
    if err != nil {
      return video, err
    }

	// Получаем PGID процесса
	pgid, err := syscall.Getpgid(cmd.Process.Pid)
	if err != nil {
        return video, err
	}

    log.Printf("Started process with PID %d and PGID %d\n", cmd.Process.Pid, pgid)

    // Обработка сигналов
    signalChan := make(chan os.Signal, 1)
    signal.Notify(signalChan, syscall.SIGINT, syscall.SIGTERM)

    go func() {
        sig := <-signalChan
        log.Printf("Received signal %s, killing process group %d\n", sig, pgid)
        syscall.Kill(-pgid, syscall.SIGKILL) // Убиваем всю группу процессов
    }()

    // Ожидание завершения команды
    err = cmd.Wait()
    if err != nil {
        return video, err
    }

    return video, nil
}
