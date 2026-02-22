<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation;

use Innmind\LabStation\Iteration;
use Innmind\CLI\{
    Environment,
    Console,
    Command\Arguments,
    Command\Options,
};
use Innmind\Immutable\Map;
use PHPUnit\Framework\TestCase;

class IterationTest extends TestCase
{
    public function testEndingAnIterationWithoutAStartWillClearTheTerminal()
    {
        $iteration = new Iteration;
        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                [],
                [],
                '/',
            ),
            new Arguments,
            new Options,
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

    public function testNormalIterationWithoutAFailureWillClearTheTerminal()
    {
        $iteration = new Iteration;
        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                [],
                [],
                '/',
            ),
            new Arguments,
            new Options,
        );

        $iteration->start();
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

    public function testNormalIterationWithAFailureWillNotClearTheTerminal()
    {
        $iteration = new Iteration;
        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                [],
                [],
                '/',
            ),
            new Arguments,
            new Options,
        );

        $iteration->start();
        $iteration->failing();
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

    public function testNormalIterationWithoutAFailureWillNotClearTheTerminalWhenExplicitlyAskToKeepOutput()
    {
        $iteration = new Iteration;
        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                ['--keep-output'],
                [],
                '/',
            ),
            new Arguments,
            new Options(Map::of(['keep-output', ''])),
        );

        $iteration->start();
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

    public function testNormalIterationWithAFailureWillNotClearTheTerminalWhenExplicitlyAskToKeepOutput()
    {
        $iteration = new Iteration;
        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                ['--keep-output'],
                [],
                '/',
            ),
            new Arguments,
            new Options(Map::of(['keep-output', ''])),
        );

        $iteration->start();
        $iteration->failing();
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

    public function testClearTheTerminalEvenWhenPreviousIterationFailed()
    {
        $iteration = new Iteration;
        $console = Console::of(
            Environment::inMemory(
                [],
                true,
                [],
                [],
                '/',
            ),
            new Arguments,
            new Options,
        );

        $iteration->start();
        $iteration->failing();
        $console = $iteration->end($console);
        $iteration->start();
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
