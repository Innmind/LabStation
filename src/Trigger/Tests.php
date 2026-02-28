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
use Innmind\Server\Control\Server\{
    Command,
    Process,
};
use Innmind\Filesystem\Name;
use Innmind\Immutable\{
    Map,
    Set,
    Attempt,
    Str,
    SideEffect,
};

final class Tests implements Trigger
{
    private Iteration $iteration;

    public function __construct(Iteration $iteration)
    {
        $this->iteration = $iteration;
    }

    #[\Override]
    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Activity $activity,
        Set $triggers,
    ): Attempt {
        if (!$triggers->contains(Triggers::tests)) {
            return Attempt::result($console);
        }

        return match ($activity) {
            Activity::sourcesModified => $this->attempt($console, $os),
            Activity::testsModified => $this->attempt($console, $os),
            Activity::fixturesModified => $this->attempt($console, $os),
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
                    ->get(Name::of('phpunit.xml.dist'))
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
                Command::foreground('vendor/bin/phpunit')
                    ->withOption('colors', 'always')
                    ->withOption('fail-on-warning')
                    ->withWorkingDirectory($console->workingDirectory())
                    ->withEnvironments($variables),
            )
            ->eitherWay(
                fn($process) => $this->phpunit($process, $console, $os),
                static fn() => $console->output(Str::of("Failed to run PHPUnit\n")),
            );
    }

    /**
     * @return Attempt<Console>
     */
    private function phpunit(
        Process $process,
        Console $console,
        OperatingSystem $os,
    ): Attempt {
        $console = $process
            ->output()
            ->map(static fn($chunk) => $chunk->data())
            ->sink($console)
            ->attempt(static fn(Console $console, $line) => $console->output($line))
            ->match(
                static fn($console) => $console,
                static fn($e) => $e,
            );
        $successful = $process->wait()->match(
            static fn() => true,
            static fn() => false,
        );

        if (!$successful) {
            $this->iteration->failing();
        }

        if ($console instanceof \Throwable) {
            return Attempt::error($console);
        }

        if ($console->options()->contains('silent')) {
            return Attempt::result($console);
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
            ->flatMap(
                static fn($process) => $process
                    ->wait()
                    ->attempt(static fn() => new \Exception)
                    ->recover(static fn() => Attempt::result(SideEffect::identity)),
            )
            ->map(static fn() => $console);
    }
}
