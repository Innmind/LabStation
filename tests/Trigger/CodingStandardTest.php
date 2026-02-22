<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\CodingStandard,
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
use PHPUnit\Framework\TestCase;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set as DataSet,
};

class CodingStandardTest extends TestCase
{
    use BlackBox;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new CodingStandard(
                new Iteration,
            ),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new CodingStandard(new Iteration);

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
            Set::of(Triggers::codingStandard),
        ));
    }

    public function testDoNothingWhenToolNotInstalled()
    {
        $trigger = new CodingStandard(new Iteration);

        $os = OperatingSystem::new(
            Config::new()->mountFilesystemVia(
                static fn() => Attempt::result(Adapter::inMemory()),
            ),
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
            Set::of(Triggers::codingStandard),
        ));
    }

    public function testDoNothingWhenTriggerNotEnabled()
    {
        $trigger = new CodingStandard(new Iteration);

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
        $this
            ->forAll(DataSet::of(
                Activity::sourcesModified,
                Activity::proofsModified,
                Activity::testsModified,
                Activity::fixturesModified,
                Activity::propertiesModified,
            ))
            ->then(function($activity) {
                $trigger = new CodingStandard(
                    $iteration = new Iteration,
                );
                $adapter = Adapter::inMemory();
                $_ = $adapter->add(File::named(
                    '.php_cs.dist',
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
                                        0 => "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'",
                                        1 => "say 'Coding Standard : right'",
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
                    $activity,
                    Set::of(Triggers::codingStandard),
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
            });
    }

    public function testDoesnClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new CodingStandard(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            '.php_cs.dist',
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
                                0 => "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'",
                                1 => "say 'Coding Standard : right'",
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
            Set::of(Triggers::codingStandard),
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

    public function testTriggerForPHPCSFixer3()
    {
        $trigger = new CodingStandard(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            '.php-cs-fixer.dist.php',
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
                                0 => "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run'",
                                1 => "say 'Coding Standard : right'",
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
            Activity::testsModified,
            Set::of(Triggers::codingStandard),
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

    public function testSaidMessageIsChangedWhenTestsAreFailing()
    {
        $trigger = new CodingStandard(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            '.php_cs.dist',
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
                                0 => "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'",
                                1 => "say 'Coding Standard : wrong'",
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
            Set::of(Triggers::codingStandard),
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
        $trigger = new CodingStandard(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            '.php_cs.dist',
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
                                0 => "vendor/bin/php-cs-fixer 'fix' '--diff' '--dry-run' '--diff-format' 'udiff'",
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
            Set::of(Triggers::codingStandard),
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
