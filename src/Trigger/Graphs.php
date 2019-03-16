<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Server\Control\Server\{
    Processes,
    Command,
};
use Innmind\CLI\Environment;
use Innmind\Json\Json;
use Innmind\Url\PathInterface;
use Innmind\Stream\Writable;
use Innmind\Immutable\Str;

final class Graphs implements Trigger
{
    private $filesystem;
    private $processes;
    private $tmp;

    public function __construct(
        Filesystem $filesystem,
        Processes $processes,
        PathInterface $tmp
    ) {
        $this->filesystem = $filesystem;
        $this->processes = $processes;
        $this->tmp = $tmp;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (!$activity->is(Type::start())) {
            return;
        }

        $name = $this->load($env);
        [$vendor, $package] = $name->split('/');

        $this->open(
            Command::foreground('dependency-graph')
                ->withArgument('depends-on')
                ->withArgument((string) $name)
                ->withArgument((string) $vendor)
                ->withWorkingDirectory((string) $this->tmp),
            $env->error()
        );
        $this->open(
            Command::foreground('dependency-graph')
                ->withArgument('of')
                ->withArgument((string) $name)
                ->withWorkingDirectory((string) $this->tmp),
            $env->error()
        );
        $this->open(
            Command::foreground('dependency-graph')
                ->withArgument('vendor')
                ->withArgument((string) $vendor)
                ->withWorkingDirectory((string) $this->tmp),
            $env->error()
        );
    }

    private function load(Environment $env): Str
    {
        $composer = $this
            ->filesystem
            ->mount($env->workingDirectory())
            ->get('composer.json')
            ->content();
        $package = Json::decode((string) $composer);

        return Str::of($package['name']);
    }

    private function open(Command $command, Writable $error): void
    {
        $process = $this->processes->execute($command)->wait();

        if (!$process->exitCode()->isSuccessful()) {
            $lines = Str::of((string) $process->output())->split("\n");
            $lines->dropEnd(1)->foreach(static function($line) use ($error): void {
                $error->write($line->append("\n"));
            });
            $error->write($lines->last());

            return;
        }

        $this
            ->processes
            ->execute(
                Command::foreground('open')
                    ->withArgument((string) $process->output())
                    ->withWorkingDirectory((string) $this->tmp)
            )
            ->wait();
    }
}
