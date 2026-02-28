<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Async\Scheduler;
use Innmind\Immutable\{
    Set,
    Sequence,
    Attempt,
};

final class Monitor
{
    private OperatingSystem $os;
    private Iteration $iteration;
    private Trigger $trigger;
    /** @var Sequence<Agent> */
    private Sequence $agents;

    /**
     * @no-named-arguments
     */
    public function __construct(
        OperatingSystem $os,
        Iteration $iteration,
        Trigger $trigger,
        Agent ...$agents,
    ) {
        $this->os = $os;
        $this->iteration = $iteration;
        $this->trigger = $trigger;
        $this->agents = Sequence::of(...$agents);
    }

    /**
     * @param Set<Triggers> $triggers
     *
     * @return Attempt<Console>
     */
    public function __invoke(Console $console, Set $triggers): Attempt
    {
        $project = $console->workingDirectory();
        $scheduler = Scheduler::of($this->os);
        $activities = Activities::new(
            $this->trigger,
            $this->iteration,
            $triggers,
        );

        return $scheduler
            ->sink(Attempt::result($console))
            ->with(
                new Monitor\Loop(
                    $this->agents,
                    $activities,
                    $project,
                ),
            );
    }
}
