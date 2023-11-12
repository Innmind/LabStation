<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\TimeContinuum\Earth\Period\Millisecond;
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
        // If no activities yet we wait a little bit to avoid always calling
        // this method.
        // The better approach would be to use sockets and to monitor them so we
        // would call the trigger as soon as an activity occured but the agents
        // watch directories and the underlyin mecanism runs every second so
        // there is always this delay. And if we use sockets we still need to
        // exit this method periodically to allow the source to be restarted in
        // order to check if any agent crashed in order to restart it.
        if (\count($this->activities) === 0) {
            $os->process()->halt(Millisecond::of(500));
        }

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
            [Activity::start],
        );
    }

    public function push(Activity $activity): self
    {
        $this->activities[] = $activity;

        return $this;
    }

    /**
     * Used for tests only
     *
     * @return list<Activity>
     */
    public function toList(): array
    {
        return $this->activities;
    }
}
