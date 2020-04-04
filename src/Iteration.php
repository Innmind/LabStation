<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Environment;
use Innmind\Immutable\Str;

final class Iteration
{
    private bool $shouldClearTerminal = true;

    public function start(): void
    {
        $this->shouldClearTerminal = true;
    }

    public function failing(): void
    {
        $this->shouldClearTerminal = false;
    }

    public function end(Environment $env): void
    {
        if (!$this->shouldClearTerminal) {
            return;
        }

        if ($env->arguments()->contains('--keep-output')) {
            return;
        }

        // clear terminal
        $env->output()->write(Str::of("\033[2J\033[H"));
    }
}
