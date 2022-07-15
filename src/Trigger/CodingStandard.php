<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
    Iteration,
};
use Innmind\CLI\Console;
use Innmind\Server\Control\Server\{
    Processes,
    Command,
    Process\Output,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\Name;
use Innmind\Immutable\Map;

final class CodingStandard implements Trigger
{
    private Processes $processes;
    private Filesystem $filesystem;
    private Iteration $iteration;

    public function __construct(
        Processes $processes,
        Filesystem $filesystem,
        Iteration $iteration,
    ) {
        $this->processes = $processes;
        $this->filesystem = $filesystem;
        $this->iteration = $iteration;
    }

    public function __invoke(Activity $activity, Console $console): Console
    {
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
        $directory = $this->filesystem->mount($console->workingDirectory());

        return $directory
            ->get(new Name('.php_cs.dist'))
            ->otherwise(static fn() => $directory->get(new Name('.php-cs-fixer.dist.php')))
            ->match(
                fn($file) => $this->run($console, $file->name()),
                static fn() => $console,
            );
    }

    private function run(Console $console, Name $file): Console
    {
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

        $process = $this
            ->processes
            ->execute($command);
        $console = $process
            ->output()
            ->reduce(
                $console,
                static fn(Console $console, $line, $type) => match ($type) {
                    Output\Type::output => $console->output($line),
                    Output\Type::error => $console->error($line),
                },
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
