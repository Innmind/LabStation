<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Monitor;

use Innmind\LabStation\{
    Agent,
    Activities,
};
use Innmind\Mantle\{
    Source\Continuation,
    Task,
};
use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Sequence,
    Set,
    Predicate\Instance,
};

final class Loop
{
    /** @var Sequence<Agent> */
    private Sequence $agents;
    private Activities $activities;
    private Path $project;

    /**
     * @param Sequence<Agent> $agents
     */
    public function __construct(
        Sequence $agents,
        Activities $activities,
        Path $project,
    ) {
        $this->agents = $agents;
        $this->activities = $activities;
        $this->project = $project;
    }

    /**
     * @param array{Console, boolean} $carry
     * @param Continuation<array{Console, boolean}, ?Agent> $continuation
     * @param Sequence<?Agent> $crashed
     *
     * @return Continuation<array{Console, boolean}, ?Agent>
     */
    public function __invoke(
        array $carry,
        OperatingSystem $os,
        Continuation $continuation,
        Sequence $crashed,
    ): Continuation {
        [$console, $started] = $carry;

        if (!$started) {
            return $continuation
                ->launch($this->agents->map($this->buildTask(...)))
                ->carryWith([$console, true]);
        }

        $continuation = $continuation->launch(
            $crashed
                ->keep(Instance::of(Agent::class))
                ->map($this->buildTask(...)),
        );

        $console = ($this->activities)(
            $console,
            $os,
        );

        return $continuation->carryWith([$console, $started]);
    }

    /**
     * @return Task<?Agent>
     */
    private function buildTask(Agent $agent): Task
    {
        return Task::of(fn($os) => $agent(
            $os,
            $this->project,
            $this->activities,
        ));
    }
}
