package main

import (
    "log"
    "os"
    "net/http"
    "net/url"
    "strings"
    "encoding/json"
    "app/share"
    "fmt"
)

const downloadFolder = "./downloads"
const queue_source = "wall_post"
const queue_destination = "image_download"

// VK API configuration
var (
    workDir              string
    vkAccessToken        string
    vkGroupID            string
    vkOwnerID            string
    postWallURL  = "https://api.vk.com/method/wall.post?v=5.199"
)


func init() {
    vkAccessToken = os.Getenv("VK_ACCESS_USER_TOKEN")
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

    log.Println("wall post video: ", video.VideoID)

    data := url.Values{}
    data.Set("owner_id", vkOwnerID)
    data.Set("from_group", "1")
    data.Set("attachments", fmt.Sprintf("video%s_%d", vkOwnerID, video.VkVideoID))

    r, err := http.NewRequest("POST", postWallURL, strings.NewReader(data.Encode()))
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
            PostID int `json:"post_id"`
        } `json:"response"`
    }
    err = json.NewDecoder(urlResp.Body).Decode(&saveResponseR)
    if err != nil {
        log.Println(err)
        return video, err
    }
    PostID := saveResponseR.Response.PostID

    video.PostID = PostID

    return video, nil
}

