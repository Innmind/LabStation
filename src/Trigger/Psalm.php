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
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\Name;
use Innmind\Immutable\Str;

final class Psalm implements Trigger
{
    private Processes $processes;
    private Filesystem $filesystem;

    public function __construct(Processes $processes, Filesystem $filesystem)
    {
        $this->processes = $processes;
        $this->filesystem = $filesystem;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (
            !$activity->is(Type::sourcesModified()) &&
            !$activity->is(Type::testsModified())
        ) {
            return;
        }

        $directory = $this->filesystem->mount($env->workingDirectory());

        if (!$directory->contains(new Name('psalm.xml'))) {
            return;
        }

        $output = $env->output();
        $error = $env->error();

        $process = $this
            ->processes
            ->execute(
                Command::foreground('vendor/bin/psalm')
                    ->withWorkingDirectory($env->workingDirectory())
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
        $text = 'Psalm : ';
        $text .= $successful ? 'ok' : 'failing';

        $this
            ->processes
            ->execute(
                Command::foreground('say')
                    ->withArgument($text)
            )
            ->wait();
    }
}
