package main

import (
    "fmt"
    "log"
    "os/exec"
    "os"
    "path/filepath"
    "net/http"
    "net/url"
    "strings"
    "encoding/json"
    "app/share"
)

const downloadFolder = "./downloads"
const queue_source = "upload"
const queue_destination = "image_download"

// VK API configuration
var (
    workDir              string
    vkAccessToken        string
    vkGroupID            string
    vkOwnerID            string
    videoSaveURL  = "https://api.vk.com/method/video.save?v=5.199"
)


func init() {
    vkAccessToken = os.Getenv("VK_ACCESS_TOKEN")
    vkGroupID = os.Getenv("VK_GROUP_ID")
    vkOwnerID = os.Getenv("VK_OWNER_ID")

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
    filePath, err := getLocalPathByVideoId(video.VideoID)
    if err != nil {
        log.Println(err)
        return video, err
    }
    log.Println("upload video: ", video.VideoID)

    data := url.Values{}
    data.Set("name", video.Title)
    data.Set("description", video.Description)
    data.Set("group_id", vkGroupID)

    r, err := http.NewRequest("POST", videoSaveURL, strings.NewReader(data.Encode()))
    r.Header.Add("Authorization", "Bearer " + vkAccessToken)
    r.Header.Add("Content-Type", "application/x-www-form-urlencoded")

    client := &http.Client{}
    urlResp, err := client.Do(r)
    if err != nil {
        return video, err
    }
    defer urlResp.Body.Close()

    var saveResponseR struct {
        Response struct {
            UploadURL string `json:"upload_url"`
            VideoID int `json:"video_id"`
        } `json:"response"`
    }
    err = json.NewDecoder(urlResp.Body).Decode(&saveResponseR)
    if err != nil {
        log.Println(err)
        return video, err
    }
    uploadURL := saveResponseR.Response.UploadURL
    vkVideoID := saveResponseR.Response.VideoID

    //ЗАПРОС 2 оправка файла на сервера VK

    cmd := exec.Command("curl", "-F", "'video_file=@"+filePath, uploadURL)
    err = cmd.Run()
    if err != nil {
        log.Println(err)
        return video, err
    }

    video.VkVideoID = vkVideoID

    return video, nil
}

func getLocalPathByVideoId(videoID string) (string, error) {
    fmt.Errorf(downloadFolder)
    files, err := filepath.Glob(filepath.Join(downloadFolder, fmt.Sprintf("%s.*", videoID)))
    if err != nil || len(files) == 0 {
        return "", fmt.Errorf("Empty file %s", videoID)
    }
    return files[0], nil
}
