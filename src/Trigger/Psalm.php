<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
    Iteration,
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
    private Iteration $iteration;

    public function __construct(
        Processes $processes,
        Filesystem $filesystem,
        Iteration $iteration,
    ) {
        $this->processes = $processes;
        $this->filesystem = $filesystem;
        $this->iteration = $iteration;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        $_ = match ($activity->type()) {
            Type::sourcesModified => $this->run($env),
            Type::testsModified => $this->run($env),
            default => null,
        };
    }

    private function run(Environment $env): void
    {
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
        $successful = $process->exitCode()->successful();

        if (!$successful) {
            $this->iteration->failing();
        }

        if ($env->arguments()->contains('--silent')) {
            return;
        }

        $text = 'Psalm : ';
        $text .= $successful ? 'ok' : 'failing';

        $this
            ->processes
            ->execute(
                Command::foreground('say')
                    ->withArgument($text),
            )
            ->wait();
    }
}
