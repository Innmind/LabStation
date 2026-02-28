<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Time\Period;
use Innmind\Immutable\{
    Set,
    Attempt,
    Sequence,
};

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

    /**
     * @return Attempt<Console>
     */
    public function __invoke(
        Console $console,
        OperatingSystem $os,
    ): Attempt {
        // If no activities yet we wait a little bit to avoid always calling
        // this method.
        // The better approach would be to use sockets and to monitor them so we
        // would call the trigger as soon as an activity occured but the agents
        // watch directories and the underlyin mecanism runs every second so
        // there is always this delay. And if we use sockets we still need to
        // exit this method periodically to allow the source to be restarted in
        // order to check if any agent crashed in order to restart it.
        if (\count($this->activities) === 0) {
            return $os
                ->process()
                ->halt(Period::millisecond(500))
                ->map(static fn() => $console);
        }

        $activities = Sequence::of(...$this->activities);
        $this->activities = [];

        return $activities
            ->sink($console)
            ->attempt(function($console, $activity) use ($os) {
                $this->iteration->start();
                $console = ($this->trigger)(
                    $console,
                    $os,
                    $activity,
                    $this->triggers,
                );

                if ($activity !== Activity::start) {
                    return $console->flatMap($this->iteration->end(...));
                }

                return $console;
            });
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
