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
    private $processes;

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

        $this
            ->processes
            ->execute(
                Command::foreground('vendor/bin/phpunit')
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
