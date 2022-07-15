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

final class Psalm implements Trigger
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
            Type::sourcesModified => $this->run($console),
            Type::testsModified => $this->run($console),
            default => $console,
        };
    }

    private function run(Console $console): Console
    {
        $directory = $this->filesystem->mount($console->workingDirectory());

        if (!$directory->contains(new Name('psalm.xml'))) {
            return $console;
        }

        /** @var Map<non-empty-string, string> */
        $variables = $console->variables()->filter(
            static fn($key) => \in_array($key, ['HOME', 'USER', 'PATH'], true),
        );

        $process = $this
            ->processes
            ->execute(
                Command::foreground('vendor/bin/psalm')
                    ->withOption('no-cache')
                    ->withWorkingDirectory($console->workingDirectory())
                    ->withEnvironments($variables),
            );
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

        $text = 'Psalm : ';
        $text .= $successful ? 'ok' : 'failing';

        $this
            ->processes
            ->execute(
                Command::foreground('say')
                    ->withArgument($text),
            )
            ->wait();

        return $console;
    }
}
