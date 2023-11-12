<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Triggers,
    Activity,
    Iteration,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\Console;
use Innmind\Server\Control\Server\Command;
use Innmind\Filesystem\Name;
use Innmind\Immutable\{
    Map,
    Set,
};

final class BlackBox implements Trigger
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
        if (!$triggers->contains(Triggers::proofs)) {
            return $console;
        }

        return match ($activity) {
            Activity::sourcesModified => $this->attempt($console, $os),
            Activity::proofsModified => $this->attempt($console, $os),
            Activity::fixturesModified => $this->attempt($console, $os),
            Activity::propertiesModified => $this->attempt($console, $os),
            default => $console,
        };
    }

    private function attempt(Console $console, OperatingSystem $os): Console
    {
        return $os
            ->filesystem()
            ->mount($console->workingDirectory())
            ->get(Name::of('blackbox.php'))
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
            ->filter(static fn($key) => \in_array(
                $key,
                ['PATH', 'LC_TERMINAL'],
                true,
            ));

        $process = $os
            ->control()
            ->processes()
            ->execute(
                Command::foreground('php')
                    ->withArgument('blackbox.php')
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

        return $os
            ->control()
            ->processes()
            ->execute(
                Command::foreground('say')->withArgument(
                    'BlackBox : '. match ($successful) {
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
