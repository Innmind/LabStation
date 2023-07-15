<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Triggers,
    Activity,
    Activity\Type,
    Iteration,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\CLI\Console;
use Innmind\Server\Control\Server\{
    Processes,
    Command,
};
use Innmind\Filesystem\Name;
use Innmind\Immutable\{
    Map,
    Set,
};

final class Tests implements Trigger
{
    private Filesystem $filesystem;
    private Processes $processes;
    private Iteration $iteration;

    public function __construct(
        Filesystem $filesystem,
        Processes $processes,
        Iteration $iteration,
    ) {
        $this->filesystem = $filesystem;
        $this->processes = $processes;
        $this->iteration = $iteration;
    }

    public function __invoke(
        Activity $activity,
        Console $console,
        Set $triggers,
    ): Console {
        if (!$triggers->contains(Triggers::tests)) {
            return $console;
        }

        return match ($activity->type()) {
            Type::sourcesModified => $this->attempt($console),
            Type::testsModified => $this->attempt($console),
            Type::fixturesModified => $this->attempt($console),
            Type::propertiesModified => $this->attempt($console),
            default => $console,
        };
    }

    private function attempt(Console $console): Console
    {
        return $this
            ->filesystem
            ->mount($console->workingDirectory())
            ->get(new Name('phpunit.xml.dist'))
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

        $process = $this
            ->processes
            ->execute(
                Command::foreground('vendor/bin/phpunit')
                    ->withOption('colors', 'always')
                    ->withOption('fail-on-warning')
                    ->withWorkingDirectory($console->workingDirectory())
                    ->withEnvironments($variables),
            );
        $console = $process
            ->output()
            ->reduce(
                $console,
                static fn(Console $console, $line) => $console->output($line),
            );
        $successful = $process->wait()->match(
            static fn() => true,
            static fn() => false,
        );

        if (!$successful) {
            $this->iteration->failing();
        }

        if ($console->options()->contains('silent')) {
            return $console;
        }

        return $this
            ->processes
            ->execute(
                Command::foreground('say')->withArgument(
                    'PHPUnit : '. match ($successful) {
                        true => 'ok',
                        false => 'failing',
                    },
                ),
            )
            ->wait()
            ->match(
                static fn() => $console,
                static fn() => $console,
            );
    }
}
