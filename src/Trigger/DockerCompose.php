<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Triggers,
    Activity,
    Activity\Type,
};
use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\Server\Command;
use Innmind\Filesystem\Name;
use Innmind\Immutable\{
    Map,
    Str,
    Set,
};

final class DockerCompose implements Trigger
{
    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Activity $activity,
        Set $triggers,
    ): Console {
        if (!$triggers->contains(Triggers::dockerCompose)) {
            return $console;
        }

        return match ($activity->type()) {
            Type::start => $this->attempt($console, $os),
            default => $console,
        };
    }

    private function attempt(Console $console, OperatingSystem $os): Console
    {
        return $os
            ->filesystem()
            ->mount($console->workingDirectory())
            ->get(Name::of('docker-compose.yml'))
            ->match(
                fn() => $this->run($console, $os),
                static fn() => $console,
            );
    }

    private function run(Console $console, OperatingSystem $os): Console
    {
        /** @var Map<non-empty-string, string> */
        $variables = $console
            ->variables()
            ->filter(static fn($key) => $key === 'PATH');

        return $os
            ->control()
            ->processes()
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
