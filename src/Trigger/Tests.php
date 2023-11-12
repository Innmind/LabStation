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
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\Server\Command;
use Innmind\Filesystem\Name;
use Innmind\Immutable\{
    Map,
    Set,
};

final class Tests implements Trigger
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
        if (!$triggers->contains(Triggers::tests)) {
            return $console;
        }

        return match ($activity->type()) {
            Type::sourcesModified => $this->attempt($console, $os),
            Type::testsModified => $this->attempt($console, $os),
            Type::fixturesModified => $this->attempt($console, $os),
            Type::propertiesModified => $this->attempt($console, $os),
            default => $console,
        };
    }

    private function attempt(Console $console, OperatingSystem $os): Console
    {
        return $os
            ->filesystem()
            ->mount($console->workingDirectory())
            ->get(Name::of('phpunit.xml.dist'))
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

        $process = $os
            ->control()
            ->processes()
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

        return $os
            ->control()
            ->processes()
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
