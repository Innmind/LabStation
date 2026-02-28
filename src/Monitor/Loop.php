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
    Attempt,
    Sequence,
    Predicate\Instance,
};

final class Loop
{
    /** @var Sequence<Agent> */
    private Sequence $agents;
    private Activities $activities;
    private Path $project;
    private bool $started = false;

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
     * @param Attempt<Console> $console
     * @param Continuation<Attempt<Console>> $continuation
     *
     * @return Continuation<Attempt<Console>>
     */
    public function __invoke(
        Attempt $console,
        OperatingSystem $os,
        Continuation $continuation,
    ): Continuation {
        if (!$this->started) {
            $this->started = true;

            return $continuation
                ->schedule($this->agents->map($this->buildTask(...)))
                ->carryWith($console);
        }

        $continuation = $continuation->schedule(
            $continuation
                ->results() // crashed agents
                ->keep(Instance::of(Agent::class))
                ->map($this->buildTask(...)),
        );

        return $console->match(
            fn($console) => $continuation->carryWith(
                ($this->activities)($console, $os),
            ),
            static fn($e) => $continuation
                ->carryWith(Attempt::error($e))
                ->terminate(),
        );
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
