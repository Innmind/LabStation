<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
use Innmind\Immutable\{
    Str,
    Attempt,
};

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

    /**
     * @return Attempt<Console>
     */
    public function end(Console $console): Attempt
    {
        if (!$this->shouldClearTerminal) {
            return Attempt::result($console);
        }

        if ($console->options()->contains('keep-output')) {
            return Attempt::result($console);
        }

        // clear terminal
        return $console->output(Str::of("\033[2J\033[H"));
    }
}
