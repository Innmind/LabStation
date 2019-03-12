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
        if (!$activity->is(Type::sourcesModified())) {
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
                    $output->write($line);
                } else {
                    $error->write($line);
                }
            });
    }
}