<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent,
    Protocol,
    Activity,
    Activity\Type,
};
use Innmind\FileWatch\Watch;
use Innmind\IPC\{
    IPC,
    Process\Name,
};
use Innmind\Url\{
    PathInterface,
    Path,
};

final class WatchTests implements Agent
{
    private Protocol $protocol;
    private Watch $watch;
    private IPC $ipc;
    private Name $monitor;

    public function __construct(
        Protocol $protocol,
        Watch $watch,
        IPC $ipc,
        Name $monitor
    ) {
        $this->protocol = $protocol;
        $this->watch = $watch;
        $this->ipc = $ipc;
        $this->monitor = $monitor;
    }

    public function __invoke(PathInterface $project): void
    {
        $tests = new Path($project.'/tests');

        ($this->watch)($tests)(function() {
            $monitor = $this->ipc->get($this->monitor);
            $monitor->send(
                $this->protocol->encode(
                    new Activity(Type::testsModified(), [])
                )
            );
            $monitor->close();
        });
    }
}
