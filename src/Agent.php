<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\Url\Path;

interface Agent
{
    public function __invoke(Path $project): void;
}
