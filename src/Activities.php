<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Immutable\Set;

final class Activities
{
    private Trigger $trigger;
    private Iteration $iteration;
    /** @var Set<Triggers> */
    private Set $triggers;
    /** @var list<Activity> */
    private array $activities;

    /**
     * @param Set<Triggers> $triggers
     * @param list<Activity> $activities
     */
    private function __construct(
        Trigger $trigger,
        Iteration $iteration,
        Set $triggers,
        array $activities,
    ) {
        $this->trigger = $trigger;
        $this->iteration = $iteration;
        $this->triggers = $triggers;
        $this->activities = $activities;
    }

    public function __invoke(
        Console $console,
        OperatingSystem $os,
    ): Console {
        while ($activity = \array_shift($this->activities)) {
            $this->iteration->start();
            $console = ($this->trigger)(
                $console,
                $os,
                $activity,
                $this->triggers,
            );
            $console = $this->iteration->end($console);
        }

        return $console;
    }

    /**
     * @param Set<Triggers> $triggers
     */
    public static function new(
        Trigger $trigger,
        Iteration $iteration,
        Set $triggers,
    ): self {
        return new self(
            $trigger,
            $iteration,
            $triggers,
            [new Activity(Activity\Type::start)],
        );
    }

    public function push(Activity $activity): self
    {
        $this->activities[] = $activity;

        return $this;
    }
}
