<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
use Innmind\Immutable\Set;

interface Trigger
{
    /**
     * @param Set<Triggers> $triggers
     */
    public function __invoke(
        Activity $activity,
        Console $console,
        Set $triggers,
    ): Console;
}
