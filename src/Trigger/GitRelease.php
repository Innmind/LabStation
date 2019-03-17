<?php
declare(strict_types = 1);

namespace Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger,
    Activity,
    Activity\Type,
    Exception\DontRelease,
};
use Innmind\CLI\{
    Environment,
    Question\Question,
    Question\ChoiceQuestion,
};
use Innmind\Git\{
    Git,
    Message,
    Exception\DomainException,
};
use Innmind\GitRelease\{
    Version,
    Release,
    LatestVersion,
};
use Innmind\Immutable\{
    Map,
    Str,
};

final class GitRelease implements Trigger
{
    private $git;
    private $release;
    private $latestVersion;

    public function __construct(Git $git, Release $release, LatestVersion $latestVersion)
    {
        $this->git = $git;
        $this->release = $release;
        $this->latestVersion = $latestVersion;
    }

    public function __invoke(Activity $activity, Environment $env): void
    {
        if (!$activity->is(Type::gitBranchChanged())) {
            return;
        }

        if ($activity->data()['branch'] !== 'master') {
            return;
        }

        $repository = $this->git->repository($env->workingDirectory());
        $version = ($this->latestVersion)($repository);

        $env->output()->write(Str::of("Current release: $version\n"));

        try {
            $newVersion = $this->askKind($env, $version);
        } catch (DontRelease $e) {
            return;
        }

        $env->output()->write(Str::of("Next release: $newVersion\n"));

        try {
            $message = $this->askMessage($env);
        } catch (DontRelease $e) {
            return;
        }

        ($this->release)($repository, $newVersion, $message);
    }

    private function askKind(Environment $env, Version $version): Version
    {
        $ask = new ChoiceQuestion(
            'Kind of release:',
            Map::of('scalar', 'scalar')
                (1, 'major')
                (2, 'minor')
                (3, 'bugfix')
                (4, 'none')
        );
        $response = $ask($env->input(), $env->output());

        if ($response->empty()) {
            throw new DontRelease;
        }

        switch ($response->current()) {
            case 'major':
                return $version->increaseMajor();

            case 'minor':
                return $version->increaseMinor();

            case 'bugfix':
                return $version->increaseBugfix();
        }

        throw new DontRelease;
    }

    private function askMessage(Environment $env): Message
    {
        $message = (new Question('message:'))($env->input(), $env->output());

        try {
            return new Message((string) $message);
        } catch (DomainException $e) {
            $env->error()->write(Str::of("Invalid message\n"));

            throw new DontRelease;
        }
    }
}
