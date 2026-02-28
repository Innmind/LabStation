<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\ComposerUpdate,
    Trigger,
    Triggers,
    Activity,
};
use Innmind\OperatingSystem\{
    OperatingSystem,
    Config,
};
use Innmind\Server\Control\{
    Server,
    Server\Process\Builder,
};
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\Immutable\{
    Set,
    Attempt,
};
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class ComposerUpdateTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new ComposerUpdate,
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new ComposerUpdate;

        $os = OperatingSystem::new();
        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );

        $this->assertSame($console, $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::composerUpdate),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $trigger = new ComposerUpdate;

        $os = OperatingSystem::new();
        $console = Console::of(
            Environment::inMemory(
                ["\n"],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );

        $console = $trigger(
            $console,
            $os,
            Activity::start,
            Set::of(),
        );
        $this->assertSame(
            [],
            $console
                ->environment()
                ->outputted()
                ->map(static fn($chunk) => $chunk[0]->toString())
                ->toList(),
        );
    }

    public function testTriggerUpdateOnStart()
    {
        $trigger = new ComposerUpdate;

        $os = OperatingSystem::new(
            Config::new()->useServerControl(Server::via(
                function($command) {
                    $this->assertSame(
                        "composer '--ansi' 'update'",
                        $command->toString(),
                    );
                    $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                        static fn($path) => $path->toString(),
                        static fn() => null,
                    ));

                    return Attempt::result(
                        Builder::foreground(2)
                            ->success([
                                ['some output', 'output'],
                                ['some error', 'error'],
                            ])
                            ->build(),
                    );
                },
            )),
        );

        $console = Console::of(
            Environment::inMemory(
                ["\n"],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );

        $console = $trigger(
            $console,
            $os,
            Activity::start,
            Set::of(Triggers::composerUpdate),
        );
        $this->assertSame(
            ['Update dependencies? [Y/n] ', 'some output', 'some error', "Dependencies updated!\n"],
            $console
                ->environment()
                ->outputted()
                ->map(static fn($chunk) => $chunk[0]->toString())
                ->toList(),
        );
    }

    public function testDoesntTriggerUpdateWhenNegativeResponse()
    {
        $trigger = new ComposerUpdate;

        $os = OperatingSystem::new();
        $console = Console::of(
            Environment::inMemory(
                ["n\n"],
                true,
                [],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options,
        );

        $console = $trigger(
            $console,
            $os,
            Activity::start,
            Set::of(Triggers::composerUpdate),
        );
        $this->assertSame(
            ['Update dependencies? [Y/n] '],
            $console
                ->environment()
                ->outputted()
                ->map(static fn($chunk) => $chunk[0]->toString())
                ->toList(),
        );
    }
}
