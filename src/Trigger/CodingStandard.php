<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Triggers,
    Activity,
    Iteration,
};
use Innmind\CLI\Console;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\Server\Command;
use Innmind\Filesystem\Name;
use Innmind\Immutable\{
    Map,
    Set,
};

final class CodingStandard implements Trigger
{
    private Iteration $iteration;

    public function __construct(Iteration $iteration)
    {
        $this->iteration = $iteration;
    }

    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Activity $activity,
        Set $triggers,
    ): Console {
        if (!$triggers->contains(Triggers::codingStandard)) {
            return $console;
        }

        return match ($activity) {
            Activity::sourcesModified => $this->attempt($console, $os),
            Activity::testsModified => $this->attempt($console, $os),
            Activity::fixturesModified => $this->attempt($console, $os),
            Activity::propertiesModified => $this->attempt($console, $os),
            default => $console,
        };
    }

    private function attempt(Console $console, OperatingSystem $os): Console
    {
        $directory = $os->filesystem()->mount($console->workingDirectory());

        return $directory
            ->get(Name::of('.php_cs.dist'))
            ->otherwise(static fn() => $directory->get(Name::of('.php-cs-fixer.dist.php')))
            ->match(
                fn($file) => $this->run($console, $os, $file->name()),
                static fn() => $console,
            );
    }

    private function run(
        Console $console,
        OperatingSystem $os,
        Name $file,
    ): Console {
        /** @var Map<non-empty-string, string> */
        $variables = $console
            ->variables()
            ->filter(static fn($key) => $key === 'PATH');

        $command = Command::foreground('vendor/bin/php-cs-fixer')
            ->withArgument('fix')
            ->withOption('diff')
            ->withOption('dry-run')
            ->withWorkingDirectory($console->workingDirectory())
            ->withEnvironments($variables);

        if ($file->toString() === '.php_cs.dist') {
            $command = $command
                ->withOption('diff-format')
                ->withArgument('udiff');
        }

        $process = $os
            ->control()
            ->processes()
            ->execute($command);
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

        return $os
            ->control()
            ->processes()
            ->execute(
                Command::foreground('say')->withArgument(
                    'Coding Standard : '. match ($successful) {
                        true => 'right',
                        false => 'wrong',
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
