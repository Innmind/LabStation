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
use Innmind\Server\Control\Server\{
    Processes,
    Command,
    Process\Output,
};
use Innmind\Immutable\Str;

final class ComposerUpdate implements Trigger
{
    private $processes;

    public function __construct(Processes $processes)
    {
        $this->processes = $processes;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (!$activity->is(Type::start())) {
            return;
        }

        $output = $env->output();
        $error = $env->error();

        $ask = new Question('Update dependencies? [Y/n]');
        $response = (string) $ask($env->input(), $output);

        if (($response ?: 'y') === 'n') {
            return;
        }

        $this
            ->processes
            ->execute(
                Command::foreground('composer')
                    ->withOption('ansi')
                    ->withArgument('update')
                    ->withWorkingDirectory((string) $env->workingDirectory())
            )
            ->output()
            ->foreach(static function(Str $line, Output\Type $type) use ($output, $error): void {
                if ($type === Output\Type::output()) {
                    $stream = $output;
                } else {
                    $stream = $error;
                }

                if (!$line->contains("\n")) {
                    $stream->write($line);

                    return;
                }

                $lines = $line->split("\n");
                $lines->dropEnd(1)->foreach(static function($line) use ($stream): void {
                    $stream->write($line->append("\n"));
                });
                $stream->write($lines->last());
            });
    }
}
