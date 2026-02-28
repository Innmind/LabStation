<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Command;

use Innmind\LabStation\{
    Monitor,
    Triggers,
};
use Innmind\CLI\{
    Command,
    Command\Usage,
    Console,
};
use Innmind\Immutable\{
    Attempt,
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

    #[\Override]
    public function __invoke(Console $console): Attempt
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
    #[\Override]
    public function usage(): Usage
    {
        return Usage::parse(<<<USAGE
        work --silent --keep-output --triggers=

        The triggers option can contain a comma separated list of values.

        Triggers can contain : cs, composer, docker, psalm and tests
        USAGE);
    }
}
