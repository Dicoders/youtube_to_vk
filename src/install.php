<?php

$db = new PDO('sqlite:' . dirname(__FILE__) . '/../data/db/videos.db');

//Создаем базу если её нет
$db->exec('CREATE TABLE IF NOT EXISTS videos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        channel_id INTEGER,
        video_id TEXT NOT NULL UNIQUE,
        title TEXT,
        description TEXT,
        vk_video_id INTEGER,
        vk_post_id INTEGER
    );');

$db->exec('CREATE TABLE IF NOT EXISTS worker_lock (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    locked_at DATETIME DEFAULT CURRENT_TIMESTAMP
);');

$db->exec('CREATE TABLE IF NOT EXISTS queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payload TEXT NOT NULL,
    processed INTEGER DEFAULT 0,
    priority INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);');

$db->exec('CREATE TABLE IF NOT EXISTS youtube_channels
(
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    channel               STRING(50),
    vk_group_id           STRING(20),
    vk_owner_id           STRING(20),
    vk_access_group_token STRING(250),
    vk_access_user_token  STRING(250)
);');
