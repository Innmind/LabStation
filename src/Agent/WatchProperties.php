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

final class WatchProperties implements Agent
{
    private Protocol $protocol;
    private Filesystem $filesystem;
    private IPC $ipc;
    private Name $monitor;

    public function __construct(
        Protocol $protocol,
        Filesystem $filesystem,
        IPC $ipc,
        Name $monitor,
    ) {
        $this->protocol = $protocol;
        $this->filesystem = $filesystem;
        $this->ipc = $ipc;
        $this->monitor = $monitor;
    }

    public function __invoke(Path $project): void
    {
        $properties = $project->resolve(Path::of('properties/'));

        if (!$this->filesystem->contains($properties)) {
            return;
        }

        $this->filesystem->watch($properties)(function() {
            $monitor = $this->ipc->get($this->monitor);
            $monitor->send(
                $this->protocol->encode(
                    new Activity(Type::propertiesModified, []),
                ),
            );
            $monitor->close();
        });
    }
}
