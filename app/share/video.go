package share

type Video struct {
    VideoID     string `json:"video_id"`
    Title       string `json:"title"`
    Description string `json:"description"`
    VkVideoID   int `json:"vk_video_id"`
    PostID      int `json:"vk_post_id"`
}
