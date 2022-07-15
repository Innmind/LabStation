<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\CLI\Console;
use Innmind\OperatingSystem\Filesystem;
use Innmind\Server\Control\Server\{
    Processes,
    Command,
};
use Innmind\Filesystem\Name;
use Innmind\Immutable\Map;

final class DockerCompose implements Trigger
{
    private Filesystem $filesystem;
    private Processes $processes;

    public function __construct(Filesystem $filesystem, Processes $processes)
    {
        $this->filesystem = $filesystem;
        $this->processes = $processes;
    }

    public function __invoke(Activity $activity, Console $console): Console
    {
        return match ($activity->type()) {
            Type::start => $this->run($console),
            default => $console,
        };
    }

    private function run(Console $console): Console
    {
        $project = $this->filesystem->mount($console->workingDirectory());

        if (!$project->contains(new Name('docker-compose.yml'))) {
            return $console;
        }

        /** @var Map<non-empty-string, string> */
        $variables = $console
            ->variables()
            ->filter(static fn($key) => $key === 'PATH');

        $this->processes->execute(
            Command::foreground('docker-compose')
                ->withArgument('up')
                ->withShortOption('d')
                ->withWorkingDirectory($console->workingDirectory())
                ->withEnvironments($variables),
        )->wait();

        return $console;
    }
}
