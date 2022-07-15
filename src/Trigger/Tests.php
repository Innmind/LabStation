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
use Innmind\Immutable\Map;

final class Tests implements Trigger
{
    private Processes $processes;
    private Iteration $iteration;

    public function __construct(Processes $processes, Iteration $iteration)
    {
        $this->processes = $processes;
        $this->iteration = $iteration;
    }

    public function __invoke(Activity $activity, Console $console): Console
    {
        return match ($activity->type()) {
            Type::sourcesModified => $this->run($console),
            Type::testsModified => $this->run($console),
            Type::fixturesModified => $this->run($console),
            Type::propertiesModified => $this->run($console),
            default => $console,
        };
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
