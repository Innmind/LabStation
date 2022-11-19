<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Command;

use Innmind\LabStation\{
    Monitor,
    Triggers,
};
use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\Immutable\{
    Str,
    Set,
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
        $triggers = $console
            ->options()
            ->maybe('triggers')
            ->map(Str::of(...))
            ->map(
                static fn($triggers) => $triggers
                    ->split(',')
                    ->map(static fn($trigger) => $trigger->trim()->toString())
                    ->filter(Triggers::allow(...))
                    ->map(Triggers::of(...)),
            )
            ->match(
                static fn($triggers) => $triggers->toList(),
                static fn() => Triggers::cases(),
            );

        return ($this->monitor)($console, Set::of(...$triggers));
    }

    /**
     * @psalm-pure
     */
    public function usage(): string
    {
        return <<<USAGE
        work --silent --keep-output --triggers=

        The triggers option can contain a comma separated list of values.

        Triggers can contain : cs, composer, docker, psalm and tests
        USAGE;
    }
}
