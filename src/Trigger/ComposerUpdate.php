<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\CLI\{
    Environment,
    Question\Question,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\Server\Control\Server\{
    Processes,
    Command,
    Process\Output,
};
use Innmind\Immutable\Str;

final class ComposerUpdate implements Trigger
{
    private Processes $processes;
    private Sockets $sockets;

    public function __construct(Processes $processes, Sockets $sockets)
    {
        $this->processes = $processes;
        $this->sockets = $sockets;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (!$activity->is(Type::start())) {
            return;
        }

        $output = $env->output();
        $error = $env->error();

        $ask = new Question('Update dependencies? [Y/n]');
        $response = $ask($env, $this->sockets)->toString();

        if (($response ?: 'y') === 'n') {
            return;
        }

        $this
            ->processes
            ->execute(
                Command::foreground('composer')
                    ->withOption('ansi')
                    ->withArgument('update')
                    ->withWorkingDirectory($env->workingDirectory()),
            )
            ->output()
            ->foreach(static function(Str $line, Output\Type $type) use ($output, $error): void {
                if ($type === Output\Type::output()) {
                    $output->write($line);
                } else {
                    $error->write($line);
                }
            });
        $output->write(Str::of("Dependencies updated!\n"));
    }
}
