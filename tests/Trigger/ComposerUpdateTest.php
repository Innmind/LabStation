<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\ComposerUpdate,
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
use Innmind\OperatingSystem\Sockets;
use Innmind\Stream\{
    Writable,
    Readable,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Str,
    Sequence,
};
use PHPUnit\Framework\TestCase;

class ComposerUpdateTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new ComposerUpdate(
                $this->createMock(Processes::class),
                $this->createMock(Sockets::class),
            )
        );
    }

    public function testDoNothingWhenNotOfExpectedType()
    {
        $trigger = new ComposerUpdate(
            $processes = $this->createMock(Processes::class),
            $this->createMock(Sockets::class),
        );
        $processes
            ->expects($this->never())
            ->method('execute');

        $this->assertNull($trigger(
            new Activity(Type::sourcesModified(), []),
            $this->createMock(Environment::class)
        ));
    }

    public function testTriggerUpdateOnStart()
    {
        $trigger = new ComposerUpdate(
            $processes = $this->createMock(Processes::class),
            new Sockets\Unix,
        );
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "composer '--ansi' 'update'" &&
                    $command->workingDirectory()->toString() === '/somewhere';
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
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(Path::of('/somewhere'));
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $output
            ->expects($this->at(0))
            ->method('write')
            ->with(Str::of('Update dependencies? [Y/n] '));
        $output
            ->expects($this->at(1))
            ->method('write')
            ->with(Str::of('some output'));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('some error'));

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }

    public function testDoesntTriggerUpdateWhenNegativeResponse()
    {
        $trigger = new ComposerUpdate(
            $processes = $this->createMock(Processes::class),
            new Sockets\Unix,
        );
        $processes
            ->expects($this->never())
            ->method('execute');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('interactive')
            ->willReturn(true);
        $env
            ->expects($this->once())
            ->method('arguments')
            ->willReturn(Sequence::strings());
        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "n\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->once())
            ->method('write')
            ->with(Str::of('Update dependencies? [Y/n] '));

        $this->assertNull($trigger(
            new Activity(Type::start(), []),
            $env
        ));
    }
}
