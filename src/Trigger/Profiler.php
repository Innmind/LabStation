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
    Name,
};
use Innmind\Server\Control\Server\{
    Processes,
    Command,
};
use Innmind\Url\{
    Url,
    Path,
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

        if (!$project->contains(new Name('config'))) {
            return;
        }

        $config = $project->get(new Name('config'));

        if (!$config instanceof Directory) {
            return;
        }

        if (!$config->contains(new Name('.env'))) {
            return;
        }

        $this->start($config->get(new Name('.env')), $env->workingDirectory());
    }

    private function start(File $file, Path $workingDirectory): void
    {
        /** @var array{ENV?: string|bool, PROFILER?: string} */
        $env = $this->dotenv->parse($file->content()->toString());

        if (!\array_key_exists('DEBUG', $env)) {
            return;
        }

        if ($env['DEBUG'] != true) {
            return;
        }

        if (!\array_key_exists('PROFILER', $env)) {
            return;
        }

        $workingDirectory = $workingDirectory->resolve(Path::of('../profiler/public'));

        $this->processes->execute(
            Command::background('php')
                ->withShortOption('S')
                ->withArgument(Url::of($env['PROFILER'])->authority()->toString())
                ->withWorkingDirectory($workingDirectory),
        );
    }
}
