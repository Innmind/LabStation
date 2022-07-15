<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;

interface Trigger
{
    public function __invoke(Activity $activity, Console $console): Console;
}
