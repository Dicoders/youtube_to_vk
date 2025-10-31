<?php

use App\Handlers\Downloader;
use App\Queue;
use App\YoutubeChannels;

require(dirname(__FILE__) . '/../vendor/autoload.php');


$pdo = new PDO('sqlite:' . dirname(__FILE__) . '/../data/db/videos.db');

$queue = new Queue($pdo);

$youtubeChannels = new YoutubeChannels($pdo);
$channels = $youtubeChannels->getChannels();

foreach ($channels as $channel) {
    $url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel['channel'];

    $xml = simplexml_load_file($url);
    $xml->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');

    foreach ($xml->entry as $item) {
        $id = str_replace('yt:video:', '', (string)$item->id[0]);
        $views = (int)$item->xpath('.//media:statistics')[0]['views'];
        $description = (string)$item->xpath('.//media:description')[0];

        $title = $item->title;
        if (is_array($item->title)) {
            $title = $item->title[0];
        }

        if ($views === 0) {
            continue;
        }

        $stmt = $pdo->prepare('select id from videos where video_id=:video_id');
        $stmt->execute([
            'video_id' => $id
        ]);
        $row = $stmt->fetch(SQLITE3_ASSOC);

        if (empty($row)) {
            $stmt = $pdo->prepare('insert into videos (video_id,title,description, channel_id) values (:video_id,:title,:description, :channel_id)');
            $stmt->execute([
                'video_id' => $id,
                'title' => $title,
                'description' => $description,
                'channel_id' => $channel['id']
            ]);

            $task = [
                'channel_id' => $channel['id'],
                'video_id' => $id,
                'title' => $title,
                'description' => $description,
                'vk_video_id' => 0,
                'vk_post_id' => 0,
            ];

            $queue->push(Downloader::class, $task);
        }
    }
}
