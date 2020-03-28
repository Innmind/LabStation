<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\CLI\Environment;
use Innmind\Server\Control\Server\{
    Processes,
    Command,
    Process\Output,
};
use Innmind\Immutable\Str;

final class Tests implements Trigger
{
    private Processes $processes;

    public function __construct(Processes $processes)
    {
        $this->processes = $processes;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (
            !$activity->is(Type::sourcesModified()) &&
            !$activity->is(Type::testsModified())
        ) {
            return;
        }

        $output = $env->output();
        $error = $env->error();

        $process = $this
            ->processes
            ->execute(
                Command::foreground('vendor/bin/phpunit')
                    ->withOption('colors', 'always')
                    ->withOption('fail-on-warning')
                    ->withWorkingDirectory($env->workingDirectory()),
            );
        $process
            ->output()
            ->foreach(static function(Str $line, Output\Type $type) use ($output, $error): void {
                if ($type === Output\Type::output()) {
                    $output->write($line);
                } else {
                    $error->write($line);
                }
            });
        $process->wait();

        if ($env->arguments()->contains('--silent')) {
            return;
        }

        $successful = $process->exitCode()->isSuccessful();
        $text = 'PHPUnit : ';
        $text .= $successful ? 'ok' : 'failing';

        $this
            ->processes
            ->execute(
                Command::foreground('say')
                    ->withArgument($text),
            )
            ->wait();

        // clear terminal
        if ($successful && !$env->arguments()->contains('--keep-output')) {
            $output->write(Str::of("\033[2J\033[H"));
        }
    }
}
