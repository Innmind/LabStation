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
use Innmind\Immutable\{
    Map,
    Str,
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

    public function __invoke(Activity $activity, Console $console): Console
    {
        return match ($activity->type()) {
            Type::start => $this->attempt($console),
            default => $console,
        };
    }

    private function attempt(Console $console): Console
    {
        return $this
            ->filesystem
            ->mount($console->workingDirectory())
            ->get(new Name('docker-compose.yml'))
            ->match(
                fn() => $this->run($console),
                static fn() => $console,
            );
    }

    private function run(Console $console): Console
    {
        /** @var Map<non-empty-string, string> */
        $variables = $console
            ->variables()
            ->filter(static fn($key) => $key === 'PATH');

        return $this
            ->processes
            ->execute(
                Command::foreground('docker-compose')
                    ->withArgument('up')
                    ->withShortOption('d')
                    ->withWorkingDirectory($console->workingDirectory())
                    ->withEnvironments($variables),
            )
            ->wait()
            ->match(
                static fn() => $console,
                static fn() => $console
                    ->error(Str::of("Failed to start docker\n")),
            );
    }
}
