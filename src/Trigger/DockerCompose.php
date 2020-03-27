<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\CLI\Environment;
use Innmind\OperatingSystem\Filesystem;
use Innmind\Server\Control\Server\{
    Processes,
    Command,
};

final class DockerCompose implements Trigger
{
    private Filesystem $filesystem;
    private Processes $processes;

    public function __construct(Filesystem $filesystem, Processes $processes)
    {
        $this->filesystem = $filesystem;
        $this->processes = $processes;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (!$activity->is(Type::start())) {
            return;
        }

        $project = $this->filesystem->mount($env->workingDirectory());

        if (!$project->has('docker-compose.yml')) {
            return;
        }

        $this->processes->execute(
            Command::foreground('docker-compose')
                ->withArgument('up')
                ->withShortOption('d')
                ->withWorkingDirectory((string) $env->workingDirectory())
        );
    }
}
