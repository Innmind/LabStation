<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Monitor;

use Innmind\LabStation\{
    Agent,
    Activities,
};
use Innmind\Async\Scope\Continuation;
use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Url\Path;
use Innmind\Immutable\{
    Sequence,
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
     * @param Continuation<array{Console, boolean}> $continuation
     *
     * @return Continuation<array{Console, boolean}>
     */
    public function __invoke(
        array $carry,
        OperatingSystem $os,
        Continuation $continuation,
    ): Continuation {
        [$console, $started] = $carry;

        if (!$started) {
            return $continuation
                ->schedule($this->agents->map($this->buildTask(...)))
                ->carryWith([$console, true]);
        }

        $continuation = $continuation->schedule(
            $continuation
                ->results() // crashed agents
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
     * @return callable(OperatingSystem): ?Agent
     */
    private function buildTask(Agent $agent): callable
    {
        return fn(OperatingSystem $os) => $agent(
            $os,
            $this->project,
            $this->activities,
        );
    }
}
