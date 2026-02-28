<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
};
use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Immutable\{
    Set,
    Sequence,
    Attempt,
};

final class All implements Trigger
{
    /** @var list<Trigger> */
    private array $triggers;

    /**
     * @no-named-arguments
     */
    public function __construct(Trigger ...$triggers)
    {
        $this->triggers = $triggers;
    }

    #[\Override]
    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Activity $activity,
        Set $triggers,
    ): Attempt {
        return Sequence::of(...$this->triggers)
            ->sink($console)
            ->attempt(static fn($console, $trigger) => $trigger(
                $console,
                $os,
                $activity,
                $triggers,
            ));
    }
}
