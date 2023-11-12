<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Triggers,
    Activity,
};
use Innmind\CLI\{
    Console,
    Question\Question,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\Server\{
    Command,
    Process\Output,
};
use Innmind\Immutable\{
    Map,
    Str,
    Set,
};

final class ComposerUpdate implements Trigger
{
    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Activity $activity,
        Set $triggers,
    ): Console {
        if (!$triggers->contains(Triggers::composerUpdate)) {
            return $console;
        }

        return match ($activity) {
            Activity::start => $this->ask($console, $os),
            default => $console,
        };
    }

    private function ask(Console $console, OperatingSystem $os): Console
    {
        $ask = new Question('Update dependencies? [Y/n]');
        [$response, $console] = $ask($console);

        return $response
            ->filter(static fn($response) => match ($response->toString()) {
                'y', '' => true,
                default => false,
            })
            ->match(
                fn() => $this->run($console, $os),
                static fn() => $console,
            );
    }

    private function run(Console $console, OperatingSystem $os): Console
    {
        /** @var Map<non-empty-string, string> */
        $variables = $console->variables()->filter(
            static fn($key) => \in_array($key, ['HOME', 'USER', 'PATH'], true),
        );

        return $os
            ->control()
            ->processes()
            ->execute(
                Command::foreground('composer')
                    ->withOption('ansi')
                    ->withArgument('update')
                    ->withWorkingDirectory($console->workingDirectory())
                    ->withEnvironments($variables),
            )
            ->output()
            ->reduce(
                $console,
                static fn(Console $console, $line) => $console->output($line),
            )
            ->output(Str::of("Dependencies updated!\n"));
    }
}
