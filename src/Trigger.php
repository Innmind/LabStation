<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Immutable\{
    Set,
    Attempt,
};

interface Trigger
{
    /**
     * @param Set<Triggers> $triggers
     *
     * @return Attempt<Console>
     */
    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Activity $activity,
        Set $triggers,
    ): Attempt;
}
