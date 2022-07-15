<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
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

    public function end(Console $console): Console
    {
        if (!$this->shouldClearTerminal) {
            return $console;
        }

        if ($console->options()->contains('keep-output')) {
            return $console;
        }

        // clear terminal
        return $console->output(Str::of("\033[2J\033[H"));
    }
}
