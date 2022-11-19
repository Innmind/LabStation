<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Triggers,
    Activity,
    Activity\Type,
};
use Innmind\CLI\{
    Console,
    Question\Question,
};
use Innmind\Server\Control\Server\{
    Processes,
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
    private Processes $processes;

    public function __construct(Processes $processes)
    {
        $this->processes = $processes;
    }

    public function __invoke(
        Activity $activity,
        Console $console,
        Set $triggers,
    ): Console {
        if (!$triggers->contains(Triggers::composerUpdate)) {
            return $console;
        }

        return match ($activity->type()) {
            Type::start => $this->ask($console),
            default => $console,
        };
    }

    private function ask(Console $console): Console
    {
        $ask = new Question('Update dependencies? [Y/n]');
        [$response, $console] = $ask($console);

        return $response
            ->filter(static fn($response) => match ($response->toString()) {
                'y', '' => true,
                default => false,
            })
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

        return $this
            ->processes
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
                static fn(Console $console, $line, $type) => match ($type) {
                    Output\Type::output => $console->output($line),
                    Output\Type::error => $console->error($line),
                },
            )
            ->output(Str::of("Dependencies updated!\n"));
    }
}
