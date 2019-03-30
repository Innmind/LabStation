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
use Innmind\Git\Git;
use Innmind\GitRelease\{
    SignedRelease,
    LatestVersion,
};
use function Innmind\FileWatch\bootstrap as watch;
use function Innmind\IPC\bootstrap as ipc;

function bootstrap(OperatingSystem $os): Commands
{
    $protocol = new Protocol\Json;
    $watch = watch($os);
    $ipc = ipc($os);
    $monitor = new Name('lab-station-'.$os->process()->id());
    $git = new Git($os->control(), $os->clock());

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
                    new Trigger\ComposerUpdate($os->control()->processes()),
                    new Trigger\GitRelease(
                        $git,
                        new SignedRelease,
                        new LatestVersion
                    )
                ),
                new Agent\WatchSources(
                    $protocol,
                    $watch,
                    $ipc,
                    $monitor
                ),
                new Agent\WatchTests(
                    $protocol,
                    $watch,
                    $ipc,
                    $monitor
                ),
                new Agent\WatchCurrentGitBranch(
                    $git,
                    $protocol,
                    $watch,
                    $ipc,
                    $monitor
                )
            )
        )
    );
}
