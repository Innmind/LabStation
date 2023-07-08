<?php
declare(strict_types = 1);

namespace Innmind\LabStation;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\Commands;
use Innmind\ProcessManager\{
    Manager\Parallel,
    Runner\SubProcess,
};
use Innmind\IPC\{
    Factory as IPC,
    Process\Name,
};

function bootstrap(OperatingSystem $os): Commands
{
    $protocol = new Protocol\Json;
    $ipc = IPC::build($os);
    /** @psalm-suppress ArgumentTypeCoercion It expects a literal string */
    $monitor = Name::of('lab-station-'.$os->process()->id()->toString());

    return Commands::of(
        new Command\Work(
            new Monitor(
                $protocol,
                Parallel::of(
                    new SubProcess($os->process()),
                ),
                $ipc,
                $monitor,
                $iteration = new Iteration,
                new Trigger\All(
                    new Trigger\DockerCompose(
                        $os->filesystem(),
                        $os->control()->processes(),
                    ),
                    new Trigger\BlackBox(
                        $os->filesystem(),
                        $os->control()->processes(),
                        $iteration,
                    ),
                    new Trigger\Tests(
                        $os->filesystem(),
                        $os->control()->processes(),
                        $iteration,
                    ),
                    new Trigger\Psalm(
                        $os->control()->processes(),
                        $os->filesystem(),
                        $iteration,
                    ),
                    new Trigger\CodingStandard(
                        $os->control()->processes(),
                        $os->filesystem(),
                        $iteration,
                    ),
                    new Trigger\ComposerUpdate(
                        $os->control()->processes(),
                    ),
                ),
                new Agent\WatchSources(
                    $protocol,
                    $os->filesystem(),
                    $ipc,
                    $monitor,
                ),
                new Agent\WatchTests(
                    $protocol,
                    $os->filesystem(),
                    $ipc,
                    $monitor,
                ),
            ),
        ),
    );
}
