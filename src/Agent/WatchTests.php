<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent,
    Protocol,
    Activity,
    Activity\Type,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\IPC\{
    IPC,
    Process\Name,
};
use Innmind\Url\Path;

final class WatchTests implements Agent
{
    private Protocol $protocol;
    private Filesystem $filesystem;
    private IPC $ipc;
    private Name $monitor;

    public function __construct(
        Protocol $protocol,
        Filesystem $filesystem,
        IPC $ipc,
        Name $monitor
    ) {
        $this->protocol = $protocol;
        $this->filesystem = $filesystem;
        $this->ipc = $ipc;
        $this->monitor = $monitor;
    }

    public function __invoke(Path $project): void
    {
        $tests = $project->resolve(Path::of('tests'));

        $this->filesystem->watch($tests)(function() {
            $monitor = $this->ipc->get($this->monitor);
            $monitor->send(
                $this->protocol->encode(
                    new Activity(Type::testsModified(), []),
                ),
            );
            $monitor->close();
        });
    }
}
