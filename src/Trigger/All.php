<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
};
use Innmind\CLI\Console;
use Innmind\Immutable\Set;

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

    public function __invoke(
        Activity $activity,
        Console $console,
        Set $triggers,
    ): Console {
        foreach ($this->triggers as $trigger) {
            $console = $trigger($activity, $console, $triggers);
        }

        return $console;
    }
}
