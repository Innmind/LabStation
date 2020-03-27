<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\CLI\Environment;
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\{
    Directory,
    File,
};
use Innmind\Server\Control\Server\{
    Processes,
    Command,
};
use Innmind\Url\{
    Url,
    PathInterface,
};
use Symfony\Component\Dotenv\Dotenv;

final class Profiler implements Trigger
{
    private Filesystem $filesystem;
    private Processes $processes;
    private Dotenv $dotenv;

    public function __construct(Filesystem $filesystem, Processes $processes)
    {
        $this->filesystem = $filesystem;
        $this->processes = $processes;
        $this->dotenv = new Dotenv;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (!$activity->is(Type::start())) {
            return;
        }

        $project = $this->filesystem->mount($env->workingDirectory());

        if (!$project->has('config')) {
            return;
        }

        $config = $project->get('config');

        if (!$config instanceof Directory) {
            return;
        }

        if (!$config->has('.env')) {
            return;
        }

        $this->start($config->get('.env'), $env->workingDirectory());
    }

    private function start(File $file, PathInterface $workingDirectory): void
    {
        $env = $this->dotenv->parse((string) $file->content());

        if (!\array_key_exists('DEBUG', $env)) {
            return;
        }

        if ($env['DEBUG'] != true) {
            return;
        }

        if (!\array_key_exists('PROFILER', $env)) {
            return;
        }

        $workingDirectory = \rtrim((string) $workingDirectory, '/');
        $workingDirectory .= '/../profiler/public';

        $this->processes->execute(
            Command::background('php')
                ->withShortOption('S')
                ->withArgument((string) Url::fromString($env['PROFILER'])->authority())
                ->withWorkingDirectory($workingDirectory)
        );
    }
}
