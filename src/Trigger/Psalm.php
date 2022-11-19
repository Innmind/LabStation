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
use Innmind\CLI\Console;
use Innmind\Server\Control\Server\{
    Processes,
    Command,
    Process\Output,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\Name;
use Innmind\Immutable\{
    Map,
    Set,
};

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

    public function __invoke(
        Activity $activity,
        Console $console,
        Set $triggers,
    ): Console {
        if (!$triggers->contains(Triggers::psalm)) {
            return $console;
        }

        return match ($activity->type()) {
            Type::sourcesModified => $this->ettempt($console),
            Type::testsModified => $this->ettempt($console),
            default => $console,
        };
    }

    private function ettempt(Console $console): Console
    {
        return $this
            ->filesystem
            ->mount($console->workingDirectory())
            ->get(new Name('psalm.xml'))
            ->match(
                fn() => $this->run($console),
                static fn() => $console,
            );
    }

    private function run(Console $console): Console
    {
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

        return $this
            ->processes
            ->execute(
                Command::foreground('say')->withArgument(
                    'Psalm : '. match ($successful) {
                        true  => 'ok',
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
