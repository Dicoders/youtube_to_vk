<?php

namespace App\Handlers;
interface IWorker
{
    public function work(array $task): array;

    public function getPriority(): int;
}
