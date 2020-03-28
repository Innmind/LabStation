<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\LabStation\Activity\Type;
use Innmind\ProcessManager\Manager;
use Innmind\IPC\{
    IPC,
    Message,
    Process\Name,
};
use Innmind\CLI\Environment;

final class Monitor
{
    private Protocol $protocol;
    private Manager $manager;
    private IPC $ipc;
    private Name $name;
    private Trigger $trigger;
    /** @var list<Agent> */
    private array $agents;

    public function __construct(
        Protocol $protocol,
        Manager $manager,
        IPC $ipc,
        Name $name,
        Trigger $trigger,
        Agent ...$agents
    ) {
        $this->protocol = $protocol;
        $this->manager = $manager;
        $this->ipc = $ipc;
        $this->name = $name;
        $this->trigger = $trigger;
        $this->agents = $agents;
    }

    public function __invoke(Environment $env): void
    {
        $project = $env->workingDirectory();
        $agents = $this->manager;

        foreach ($this->agents as $agent) {
            $agents = $agents->schedule(static function() use ($agent, $project): void {
                $agent($project);
            });
        }

        $agents = $agents();
        ($this->trigger)(
            new Activity(Type::start(), []),
            $env,
        );

        $this->ipc->listen($this->name)(function(Message $message) use ($env): void {
            $activity = $this->protocol->decode($message);
            ($this->trigger)($activity, $env);
        });

        $agents->kill();
    }
}
