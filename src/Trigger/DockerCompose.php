<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Triggers,
    Activity,
};
use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\Server\Command;
use Innmind\Filesystem\Name;
use Innmind\Immutable\{
    Map,
    Str,
    Set,
    Attempt,
};

final class DockerCompose implements Trigger
{
    #[\Override]
    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Activity $activity,
        Set $triggers,
    ): Attempt {
        if (!$triggers->contains(Triggers::dockerCompose)) {
            return Attempt::result($console);
        }

        return match ($activity) {
            Activity::start => $this->attempt($console, $os),
            default => Attempt::result($console),
        };
    }

    /**
     * @return Attempt<Console>
     */
    private function attempt(Console $console, OperatingSystem $os): Attempt
    {
        return $os
            ->filesystem()
            ->mount($console->workingDirectory())
            ->flatMap(
                fn($adapter) => $adapter
                    ->get(Name::of('docker-compose.yml'))
                    ->match(
                        fn() => $this->run($console, $os),
                        static fn() => Attempt::result($console),
                    ),
            );
    }

    /**
     * @return Attempt<Console>
     */
    private function run(Console $console, OperatingSystem $os): Attempt
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
            ->flatMap(
                static fn($process) => $process
                    ->wait()
                    ->attempt(static fn() => new \Exception),
            )
            ->map(static fn() => $console)
            ->recover(static fn() => $console->error(
                Str::of("Failed to start docker\n"),
            ));
    }
}
