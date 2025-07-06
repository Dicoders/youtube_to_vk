<?php

$db = new \PDO('sqlite:' . 'data/db/videos.db');

$db->exec("
            CREATE TABLE IF NOT EXISTS queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payload TEXT NOT NULL,
                processed INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

//Создаем базу если её нет
$db->exec('CREATE TABLE IF NOT EXISTS videos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
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
