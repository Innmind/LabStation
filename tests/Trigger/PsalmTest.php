<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\Psalm,
    Trigger,
    Triggers,
    Activity,
    Iteration,
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
use Innmind\OperatingSystem\{
    OperatingSystem,
    Config,
};
use Innmind\Filesystem\{
    Adapter,
    File,
    File\Content,
};
use Innmind\Immutable\{
    Map,
    Set,
    Attempt,
};
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class PsalmTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new Psalm(new Iteration),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new Psalm(new Iteration);
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
            Activity::start,
            Set::of(Triggers::psalm),
        ));
    }

    public function testDoNothingWhenPsalmNotInstalled()
    {
        $trigger = new Psalm(new Iteration);
        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result(Adapter::inMemory())),
        );

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
            Set::of(Triggers::psalm),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $trigger = new Psalm(new Iteration);
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

        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
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

    public function testTriggerTestsSuiteWhenSourcesModified()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ))->unwrap();

        $count = 0;
        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result($adapter))
                ->useServerControl(Server::via(
                    function($command) use (&$count) {
                        $this->assertSame(
                            match ($count) {
                                0 => "vendor/bin/psalm '--no-cache'",
                                1 => "say 'Psalm : ok'",
                            },
                            $command->toString(),
                        );

                        if ($count === 0) {
                            $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                                static fn($path) => $path->toString(),
                                static fn() => null,
                            ));
                        }

                        $builder = Builder::foreground(2);
                        // we say here that psalm is successful even though we have an error in
                        // the output in order to verify the terminal is cleared on success
                        $builder = match ($count) {
                            0 => $builder->success([
                                ['some output', 'output'],
                                ['some error', 'error'],
                            ]),
                            1 => $builder,
                        };
                        ++$count;

                        return Attempt::result($builder->build());
                    },
                )),
        );

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

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(
            ['some output', 'some error', "\033[2J\033[H"],
            $console
                ->environment()
                ->outputted()
                ->map(static fn($chunk) => $chunk[0]->toString())
                ->toList(),
        );
    }

    public function testDoesnClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ))->unwrap();

        $count = 0;
        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result($adapter))
                ->useServerControl(Server::via(
                    function($command) use (&$count) {
                        $this->assertSame(
                            match ($count) {
                                0 => "vendor/bin/psalm '--no-cache'",
                                1 => "say 'Psalm : ok'",
                            },
                            $command->toString(),
                        );

                        if ($count === 0) {
                            $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                                static fn($path) => $path->toString(),
                                static fn() => null,
                            ));
                        }

                        ++$count;

                        return Attempt::result(
                            Builder::foreground(2)->build(),
                        );
                    },
                )),
        );

        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                ['--keep-output'],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options(Map::of(['keep-output', ''])),
        );

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(
            [],
            $console
                ->environment()
                ->outputted()
                ->map(static fn($chunk) => $chunk[0]->toString())
                ->toList(),
        );
    }

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ))->unwrap();

        $count = 0;
        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result($adapter))
                ->useServerControl(Server::via(
                    function($command) use (&$count) {
                        $this->assertSame(
                            match ($count) {
                                0 => "vendor/bin/psalm '--no-cache'",
                                1 => "say 'Psalm : failing'",
                            },
                            $command->toString(),
                        );

                        if ($count === 0) {
                            $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                                static fn($path) => $path->toString(),
                                static fn() => null,
                            ));
                        }

                        $builder = Builder::foreground(2);
                        $builder = match ($count) {
                            0 => $builder->failed(),
                            1 => $builder,
                        };
                        ++$count;

                        return Attempt::result($builder->build());
                    },
                )),
        );

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

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(
            [],
            $console
                ->environment()
                ->outputted()
                ->map(static fn($chunk) => $chunk[0]->toString())
                ->toList(),
        );
    }

    public function testNoMessageIsSpokenWhenUsingTheSilentOption()
    {
        $trigger = new Psalm(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            'psalm.xml',
            Content::none(),
        ))->unwrap();

        $count = 0;
        $os = OperatingSystem::new(
            Config::new()
                ->mountFilesystemVia(static fn() => Attempt::result($adapter))
                ->useServerControl(Server::via(
                    function($command) use (&$count) {
                        $this->assertSame(
                            match ($count) {
                                0 => "vendor/bin/psalm '--no-cache'",
                            },
                            $command->toString(),
                        );

                        $this->assertSame('/somewhere/', $command->workingDirectory()->match(
                            static fn($path) => $path->toString(),
                            static fn() => null,
                        ));

                        ++$count;

                        return Attempt::result(
                            Builder::foreground(2)->build(),
                        );
                    },
                )),
        );

        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                ['--silent'],
                [],
                '/somewhere',
            ),
            new Arguments,
            new Options(Map::of(['silent', ''])),
        );

        $iteration->start();
        $console = $trigger(
            $console,
            $os,
            Activity::sourcesModified,
            Set::of(Triggers::psalm),
        );
        $console = $iteration->end($console);
        $this->assertSame(
            ["\033[2J\033[H"],
            $console
                ->environment()
                ->outputted()
                ->map(static fn($chunk) => $chunk[0]->toString())
                ->toList(),
        );
    }
}
