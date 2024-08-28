package main

import (
    "log"
    "database/sql"
    "os"
    "app/share"
    "github.com/mmcdole/gofeed"
    _ "github.com/mattn/go-sqlite3"
)

const downloadFolder = "./images"
const queue_destination = "download"

var (
    feedURL string
)

func init() {
    feedURL = "https://www.youtube.com/feeds/videos.xml?channel_id="+os.Getenv("YOUTUBE_CHANNEL_ID")

    //Объявление используемых очередей
    share.DeclarateQueue(queue_destination)
}


func main() {

    // Initialize the database
    db, err := sql.Open("sqlite3", "./videos.db")
    if err != nil {
        log.Println(err)
    }
    defer db.Close()

    createTable(db)

    log.Println("Parse the RSS feed")

    err = fetchNewVideos(db)
    if err != nil {
        log.Println(err)
    }
    share.CloseConnection()
}


func createTable(db *sql.DB) {
    // Create the videos table if it doesn't exist
    createTableSQL := `CREATE TABLE IF NOT EXISTS videos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        video_id TEXT NOT NULL UNIQUE,
        title TEXT,
        description TEXT,
        vk_video_id INTEGER
    );`

    _, err := db.Exec(createTableSQL)
    if err != nil {
        log.Println(err)
    }
}

func fetchNewVideos(db *sql.DB) (error) {
    // Parse the RSS feed
    fp := gofeed.NewParser()
    feed, err := fp.ParseURL(feedURL)
    if err != nil {
        return err
    }

    // Connect to the database
    for _, entry := range feed.Items {
        videoID := entry.Extensions["yt"]["videoId"][0].Value
        title := entry.Title + " 4k 60fps REUP"
        description := entry.Extensions["media"]["group"][0].Children["description"][0].Value

        // Check if the video already exists in the database
        var id int
        err = db.QueryRow("SELECT id FROM videos WHERE video_id = ?", videoID).Scan(&id)
        if err == sql.ErrNoRows {

            video := share.Video{
                VideoID: videoID,
                Title: title,
                Description: description,
            }

            share.PushMessage(queue_destination, video)

            _, err = db.Exec("INSERT INTO videos (video_id, title, description) VALUES (?, ?, ?)", videoID, title, description)
            if err != nil {
                log.Println("Error inserting new video:", err)
            }
        } else if err != nil {
            log.Println("Error checking video existence:", err)
        }
    }

    return nil
}
