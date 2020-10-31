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

final class CodingStandard implements Trigger
{
    private Processes $processes;
    private Filesystem $filesystem;
    private Iteration $iteration;

    public function __construct(
        Processes $processes,
        Filesystem $filesystem,
        Iteration $iteration
    ) {
        $this->processes = $processes;
        $this->filesystem = $filesystem;
        $this->iteration = $iteration;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (
            !$activity->is(Type::sourcesModified()) &&
            !$activity->is(Type::testsModified()) &&
            !$activity->is(Type::fixturesModified()) &&
            !$activity->is(Type::propertiesModified())
        ) {
            return;
        }

        $directory = $this->filesystem->mount($env->workingDirectory());

        if (!$directory->contains(new Name('.php_cs.dist'))) {
            return;
        }

        $output = $env->output();
        $error = $env->error();

        $process = $this
            ->processes
            ->execute(
                Command::foreground('vendor/bin/php-cs-fixer')
                    ->withArgument('fix')
                    ->withOption('diff')
                    ->withOption('dry-run')
                    ->withOption('diff-format')
                    ->withArgument('udiff')
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
        $successful = $process->exitCode()->isSuccessful();

        if (!$successful) {
            $this->iteration->failing();
        }

        if ($env->arguments()->contains('--silent')) {
            return;
        }

        $text = 'Coding Standard : ';
        $text .= $successful ? 'right' : 'wrong';

        $this
            ->processes
            ->execute(
                Command::foreground('say')
                    ->withArgument($text)
            )
            ->wait();
    }
}
