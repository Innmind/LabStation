<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Mantle\Forerunner;
use Innmind\Immutable\{
    Set,
    Sequence,
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
     */
    public function __invoke(Console $console, Set $triggers): Console
    {
        $project = $console->workingDirectory();
        $run = Forerunner::of($this->os);
        $activities = Activities::new(
            $this->trigger,
            $this->iteration,
            $triggers,
        );
        /** @var array{Console, boolean} */
        $carry = [$console, false];

        [$console] = $run(
            $carry,
            new Monitor\Loop(
                $this->agents,
                $activities,
                $project,
            ),
        );

        return $console;
    }
}
