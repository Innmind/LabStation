<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\OperatingSystem\{
    Filesystem,
    Sockets,
};
use Innmind\Server\Control\Server\{
    Processes,
    Command,
};
use Innmind\CLI\{
    Environment,
    Question\Question,
};
use Innmind\Filesystem\Name;
use Innmind\Json\Json;
use Innmind\Url\Path;
use Innmind\Stream\Writable;
use Innmind\Immutable\Str;
use function Innmind\Immutable\unwrap;

final class Graphs implements Trigger
{
    private Filesystem $filesystem;
    private Processes $processes;
    private Sockets $sockets;
    private Path $tmp;

    public function __construct(
        Filesystem $filesystem,
        Processes $processes,
        Sockets $sockets,
        Path $tmp
    ) {
        $this->filesystem = $filesystem;
        $this->processes = $processes;
        $this->sockets = $sockets;
        $this->tmp = $tmp;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (!$activity->is(Type::start())) {
            return;
        }

        $ask = new Question('Render dependency graphs? [Y/n]');
        $response = $ask($env, $this->sockets)->toString();

        if (($response ?: 'y') === 'n') {
            return;
        }

        $name = $this->load($env);
        [$vendor, $package] = unwrap($name->split('/'));

        $this->open(
            Command::foreground('dependency-graph')
                ->withArgument('depends-on')
                ->withArgument($name->toString())
                ->withArgument($vendor->toString())
                ->withWorkingDirectory($this->tmp),
            $env->error(),
        );
        $this->open(
            Command::foreground('dependency-graph')
                ->withArgument('of')
                ->withArgument($name->toString())
                ->withWorkingDirectory($this->tmp),
            $env->error(),
        );
    }

    private function load(Environment $env): Str
    {
        $composer = $this
            ->filesystem
            ->mount($env->workingDirectory())
            ->get(new Name('composer.json'))
            ->content();
        /** @var array{name: string} */
        $package = Json::decode($composer->toString());

        return Str::of($package['name']);
    }

    private function open(Command $command, Writable $error): void
    {
        $process = $this->processes->execute($command);
        $process->wait();

        if (!$process->exitCode()->successful()) {
            $error->write(Str::of($process->output()->toString()));

            return;
        }

        $this
            ->processes
            ->execute(
                Command::foreground('open')
                    ->withArgument($process->output()->toString())
                    ->withWorkingDirectory($this->tmp),
            )
            ->wait();
    }
}
