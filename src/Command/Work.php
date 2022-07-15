<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Command;

use Innmind\LabStation\Monitor;
use Innmind\CLI\{
    Command,
    Console,
};

final class Work implements Command
{
    private Monitor $monitor;

    public function __construct(Monitor $monitor)
    {
        $this->monitor = $monitor;
    }

    public function __invoke(Console $console): Console
    {
        return ($this->monitor)($console);
    }

    /**
     * @psalm-pure
     */
    public function usage(): string
    {
        return 'work --silent --keep-output';
    }
}
