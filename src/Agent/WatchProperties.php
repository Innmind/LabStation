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
use Innmind\Immutable\{
    Sequence,
    Either,
};

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
        $properties = $project->resolve(Path::of('properties'));

        if (!$this->filesystem->contains($properties)) {
            return;
        }

        $this->filesystem->watch($properties)(
            $this->ipc,
            fn(IPC $ipc) => $ipc
                ->get($this->monitor)
                ->flatMap(fn($process) => $process->send(Sequence::of(
                    $this->protocol->encode(new Activity(Type::propertiesModified)),
                )))
                ->flatMap(static fn($process) => $process->close())
                ->either()
                ->map(static fn() => $ipc)
                ->otherwise(static fn() => Either::right($ipc)), // even if it failed to send the message continue to watch for file changes
        );
    }
}
