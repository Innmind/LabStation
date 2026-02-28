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
use Innmind\Server\Control\Server\Command;
use Innmind\Immutable\{
    Map,
    Str,
    Set,
    Attempt,
};

final class ComposerUpdate implements Trigger
{
    #[\Override]
    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Activity $activity,
        Set $triggers,
    ): Attempt {
        if (!$triggers->contains(Triggers::composerUpdate)) {
            return Attempt::result($console);
        }

        return match ($activity) {
            Activity::start => $this->ask($console, $os),
            default => Attempt::result($console),
        };
    }

    /**
     * @return Attempt<Console>
     */
    private function ask(Console $console, OperatingSystem $os): Attempt
    {
        $ask = Question::of('Update dependencies? [Y/n]');

        return $ask($console)->flatMap(function($response) use ($os) {
            [$response, $console] = $response;

            return $response
                ->maybe()
                ->filter(static fn($response) => match ($response->toString()) {
                    'y', '' => true,
                    default => false,
                })
                ->match(
                    fn() => $this->run($console, $os),
                    static fn() => Attempt::result($console),
                );
        });
    }

    /**
     * @return Attempt<Console>
     */
    private function run(Console $console, OperatingSystem $os): Attempt
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
            ->flatMap(
                static fn($process) => $process
                    ->output()
                    ->map(static fn($chunk) => $chunk->data())
                    ->sink($console)
                    ->attempt(static fn(Console $console, $line) => $console->output($line)),
            )
            ->flatMap(static fn($console) => $console->output(
                Str::of("Dependencies updated!\n"),
            ));
    }
}
