<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Environment;

interface Trigger
{
    public function __invoke(Activity $activity, Environment $env): void;
}
