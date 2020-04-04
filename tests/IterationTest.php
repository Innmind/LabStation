<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation;

use Innmind\LabStation\Iteration;
use Innmind\CLI\Environment;
use Innmind\Stream\Writable;
use Innmind\Immutable\{
    Sequence,
    Str,
};
use PHPUnit\Framework\TestCase;

class IterationTest extends TestCase
{
    public function testEndingAnIterationWithoutAStartWillClearTheTerminal()
    {
        $iteration = new Iteration;
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of("\033[2J\033[H"));

        $this->assertNull($iteration->end($env));
    }

    public function testNormalIterationWithoutAFailureWillClearTheTerminal()
    {
        $iteration = new Iteration;
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of("\033[2J\033[H"));

        $this->assertNull($iteration->start());
        $this->assertNull($iteration->end($env));
    }

    public function testNormalIterationWithAFailureWillNotClearTheTerminal()
    {
        $iteration = new Iteration;
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->never())
            ->method('output');

        $this->assertNull($iteration->start());
        $this->assertNull($iteration->failing());
        $this->assertNull($iteration->end($env));
    }

    public function testNormalIterationWithoutAFailureWillNotClearTheTerminalWhenExplicitlyAskToKeepOutput()
    {
        $iteration = new Iteration;
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings('--keep-output'));
        $env
            ->expects($this->never())
            ->method('output');

        $this->assertNull($iteration->start());
        $this->assertNull($iteration->end($env));
    }

    public function testNormalIterationWithAFailureWillNotClearTheTerminalWhenExplicitlyAskToKeepOutput()
    {
        $iteration = new Iteration;
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings('--keep-output'));
        $env
            ->expects($this->never())
            ->method('output');

        $this->assertNull($iteration->start());
        $this->assertNull($iteration->failing());
        $this->assertNull($iteration->end($env));
    }

    public function testClearTheTerminalEvenWhenPreviousIterationFailed()
    {
        $iteration = new Iteration;
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of("\033[2J\033[H"));

        $iteration->start();
        $iteration->failing();
        $iteration->end($env);
        $this->assertNull($iteration->start());
        $this->assertNull($iteration->end($env));
    }
}
