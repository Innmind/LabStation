<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\Commands;
use Innmind\ProcessManager\{
    Manager\Parallel,
    Runner\SubProcess,
};
use Innmind\IPC\Process\Name;
use function Innmind\IPC\bootstrap as ipc;

function bootstrap(OperatingSystem $os): Commands
{
    $protocol = new Protocol\Json;
    $ipc = ipc($os);
    $monitor = new Name('lab-station-'.$os->process()->id()->toString());

    return new Commands(
        new Command\Work(
            new Monitor(
                $protocol,
                new Parallel(
                    new SubProcess($os->process())
                ),
                $ipc,
                $monitor,
                new Trigger\All(
                    new Trigger\Graphs(
                        $os->filesystem(),
                        $os->control()->processes(),
                        $os->sockets(),
                        $os->status()->tmp()
                    ),
                    new Trigger\Profiler(
                        $os->filesystem(),
                        $os->control()->processes()
                    ),
                    new Trigger\DockerCompose(
                        $os->filesystem(),
                        $os->control()->processes()
                    ),
                    new Trigger\Tests($os->control()->processes()),
                    new Trigger\Psalm(
                        $os->control()->processes(),
                        $os->filesystem()
                    ),
                    new Trigger\ComposerUpdate(
                        $os->control()->processes(),
                        $os->sockets(),
                    )
                ),
                new Agent\WatchSources(
                    $protocol,
                    $os->filesystem(),
                    $ipc,
                    $monitor
                ),
                new Agent\WatchTests(
                    $protocol,
                    $os->filesystem(),
                    $ipc,
                    $monitor
                )
            )
        )
    );
}
