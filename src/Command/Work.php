<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Command;

use Innmind\LabStation\Monitor;
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};

final class Work implements Command
{
    private Monitor $monitor;

    public function __construct(Monitor $monitor)
    {
        $this->monitor = $monitor;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        ($this->monitor)($env);
    }

    public function toString(): string
    {
        return 'work';
    }
}
