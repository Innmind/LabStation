<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\Tests,
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\Server\Control\Server\{
    Processes,
    Process,
    Process\Output,
};
use Innmind\CLI\Environment;
use Innmind\Stream\Writable;
use Innmind\Url\Path;
use Innmind\Immutable\Str;
use PHPUnit\Framework\TestCase;

class TestsTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new Tests($this->createMock(Processes::class))
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class)
        );
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $this->createMock(Environment::class)
        ));
    }

    public function testTriggerTestsSuiteWhenSourcesModified()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class)
        );
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === 'vendor/bin/phpunit' &&
                    $command->workingDirectory() === '/somewhere';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('foreach')
            ->with($this->callback(static function($listen): bool {
                $listen(Str::of('some output'), Output\Type::output());
                $listen(Str::of('some error'), Output\Type::error());

                return true;
            }));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/somewhere'));
        $env
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some output'));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some error'));

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $env
        ));
    }

    public function testTriggerTestsSuiteWhenTestsModified()
    {
        $trigger = new Tests(
            $processes = $this->createMock(Processes::class)
        );
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === 'vendor/bin/phpunit' &&
                    $command->workingDirectory() === '/somewhere';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('foreach')
            ->with($this->callback(static function($listen): bool {
                $listen(Str::of('some output'), Output\Type::output());
                $listen(Str::of('some error'), Output\Type::error());

                return true;
            }));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/somewhere'));
        $env
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some output'));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some error'));

        $this->assertNull($trigger(
            new Activity(Type::testsModified(), []),
            $env
        ));
    }
}
