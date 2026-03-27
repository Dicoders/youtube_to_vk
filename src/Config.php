<?php

namespace App;

class Config
{
    public const string DIR_DOWNLOADS = '/app/data/downloads/';
    public const string DIR_IMAGES    = '/app/data/images/';
    public const string PATH_COOKIES  = '/app/data/cookies/cookies.txt';

    public const int LOCK_TIMEOUT_SECONDS = 60 * 60 * 2; // 2 часа — после этого лок считается зависшим
    public const int MAX_ATTEMPTS         = 20;
}
