<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\Url\PathInterface;

interface Agent
{
    public function __invoke(PathInterface $project): void;
}
