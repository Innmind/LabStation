<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Url\Path;

interface Agent
{
    /**
     * In case of a crash return the agent instance to be able to restart it
     */
    public function __invoke(
        OperatingSystem $os,
        Path $project,
        Activities $activities,
    ): ?self;
}
