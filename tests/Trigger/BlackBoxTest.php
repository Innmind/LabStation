<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\BlackBox,
    Trigger,
    Triggers,
    Activity,
    Iteration,
};
use Innmind\OperatingSystem\{
    OperatingSystem,
    Config,
};
use Innmind\Server\Control\{
    Server,
    Server\Process\Builder,
    Server\Process\Output,
};
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\Filesystem\{
    Adapter,
    File,
    File\Content,
};
use Innmind\Immutable\{
    Map,
    Set as ISet,
    Attempt,
};
use Innmind\BlackBox\{
    PHPUnit\BlackBox as BB,
    PHPUnit\Framework\TestCase,
    Set,
};

class BlackBoxTest extends TestCase
{
    use BB;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new BlackBox(new Iteration),
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new BlackBox(new Iteration);

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
            ISet::of(Triggers::tests),
        )->unwrap());
    }

    public function testDoNothingWhenTriggerNotEnabled(): BB\Proof
    {
        return $this
            ->forAll(Set::of(...Activity::cases()))
            ->prove(function($type) {
                $trigger = new BlackBox(new Iteration);

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
                    $type,
                    ISet::of(),
                )->unwrap();
                $this->assertSame(
                    [],
                    $console
                        ->environment()
                        ->outputted()
                        ->map(static fn($chunk) => $chunk[0]->toString())
                        ->toList(),
                );
            });
    }

    public function testTriggerTestsSuiteWhenActivity(): BB\Proof
    {
        return $this
            ->forAll(Set::of(
                Activity::sourcesModified,
                Activity::proofsModified,
                Activity::fixturesModified,
                Activity::propertiesModified,
            ))
            ->prove(function($type) {
                $trigger = new BlackBox(
                    $iteration = new Iteration,
                );
                $adapter = Adapter::inMemory();
                $_ = $adapter->add(File::named(
                    'blackbox.php',
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
                                        0 => "php 'blackbox.php'",
                                        1 => "say 'BlackBox : ok'",
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
                                // we say here that tests are successful even though we have an
                                // error in the output in order to verify the terminal is cleared
                                // on success
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
                    $type,
                    ISet::of(Triggers::proofs),
                )->unwrap();
                $console = $iteration->end($console)->unwrap();
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

    public function testDoesntTriggerWhenNoBlackBoxFile(): BB\Proof
    {
        return $this
            ->forAll(Set::of(
                Activity::sourcesModified,
                Activity::proofsModified,
                Activity::fixturesModified,
                Activity::propertiesModified,
            ))
            ->prove(function($type) {
                $trigger = new BlackBox(
                    $iteration = new Iteration,
                );

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

                $iteration->start();
                $console = $trigger(
                    $console,
                    $os,
                    $type,
                    ISet::of(Triggers::proofs),
                )->unwrap();
                $console = $iteration->end($console)->unwrap();
                $this->assertSame(
                    ["\033[2J\033[H"],
                    $console
                        ->environment()
                        ->outputted()
                        ->map(static fn($chunk) => $chunk[0]->toString())
                        ->toList(),
                );
            });
    }

    public function testDoesntClearTerminalOnSuccessfullTestWhenSpecifiedOptionProvided()
    {
        $trigger = new BlackBox(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            'blackbox.php',
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
                                0 => "php 'blackbox.php'",
                                1 => "say 'BlackBox : ok'",
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
            ISet::of(Triggers::proofs),
        )->unwrap();
        $console = $iteration->end($console)->unwrap();
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
        $trigger = new BlackBox(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            'blackbox.php',
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
                                0 => "php 'blackbox.php'",
                                1 => "say 'BlackBox : failing'",
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
            ISet::of(Triggers::proofs),
        )->unwrap();
        $console = $iteration->end($console)->unwrap();
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
        $trigger = new BlackBox(
            $iteration = new Iteration,
        );
        $adapter = Adapter::inMemory();
        $_ = $adapter->add(File::named(
            'blackbox.php',
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
                                0 => "php 'blackbox.php'",
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
            ISet::of(Triggers::proofs),
        )->unwrap();
        $console = $iteration->end($console)->unwrap();
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
