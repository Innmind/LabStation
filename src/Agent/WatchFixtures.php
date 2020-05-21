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

final class WatchFixtures implements Agent
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
        $fixtures = $project->resolve(Path::of('fixtures/'));

        if (!$this->filesystem->contains($fixtures)) {
            return;
        }

        $this->filesystem->watch($fixtures)(function() {
            $monitor = $this->ipc->get($this->monitor);
            $monitor->send(
                $this->protocol->encode(
                    new Activity(Type::fixturesModified(), []),
                ),
            );
            $monitor->close();
        });
    }
}
