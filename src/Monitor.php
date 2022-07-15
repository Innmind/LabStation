<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\LabStation\Activity\Type;
use Innmind\ProcessManager\{
    Manager,
    Running,
    Process\Unkillable,
};
use Innmind\IPC\{
    IPC,
    Message,
    Process\Name,
};
use Innmind\CLI\Console;
use Innmind\Immutable\{
    Maybe,
    Str,
};

final class Monitor
{
    private Protocol $protocol;
    private Manager $manager;
    private IPC $ipc;
    private Name $name;
    private Iteration $iteration;
    private Trigger $trigger;
    /** @var list<Agent> */
    private array $agents;

    /**
     * @no-named-arguments
     */
    public function __construct(
        Protocol $protocol,
        Manager $manager,
        IPC $ipc,
        Name $name,
        Iteration $iteration,
        Trigger $trigger,
        Agent ...$agents,
    ) {
        $this->protocol = $protocol;
        $this->manager = $manager;
        $this->ipc = $ipc;
        $this->name = $name;
        $this->iteration = $iteration;
        $this->trigger = $trigger;
        $this->agents = $agents;
    }

    public function __invoke(Console $console): Console
    {
        $project = $console->workingDirectory();
        $manager = $this->manager;

        foreach ($this->agents as $agent) {
            $manager = $manager->schedule(static function() use ($agent, $project): void {
                $agent($project);
            });
        }

        return $manager
            ->start()
            ->maybe()
            ->flatMap(fn($agents) => $this->start($agents, $console))
            ->match(
                static fn($console) => $console
                    ->error(Str::of("Terminated\n"))
                    ->exit(1),
                static fn() => $console
                    ->error(Str::of("Unable to start the agents\n"))
                    ->exit(1),
            );
    }

    /**
     * @return Maybe<Console>
     */
    private function start(Running $agents, Console $console): Maybe
    {
        $console = ($this->trigger)(new Activity(Type::start), $console);

        $server = $this->ipc->listen($this->name);

        /** @psalm-suppress InvalidArgument */
        return $server(
            $console,
            function($message, $continuation, Console $console) {
                $activity = $this->protocol->decode($message);
                $this->iteration->start();
                $console = ($this->trigger)($activity, $console);
                $console = $this->iteration->end($console);

                return $continuation->continue($console);
            },
        )
            ->flatMap(
                static fn(Console $console) => $agents
                    ->kill()
                    ->map(static fn() => $console),
            )
            ->maybe();
    }
}
