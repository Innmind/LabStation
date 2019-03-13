<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation;

use function Innmind\LabStation\bootstrap;
use Innmind\OperatingSystem\{
    OperatingSystem,
    CurrentProcess,
};
use Innmind\Server\Status\Server\Process\Pid;
use Innmind\CLI\Commands;
use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testInvokation()
    {
        $os = $this->createMock(OperatingSystem::class);
        $os
            ->expects($this->any())
            ->method('process')
            ->willReturn($process = $this->createMock(CurrentProcess::class));
        $process
            ->expects($this->once())
            ->method('id')
            ->willReturn(new Pid(42));

        $this->assertInstanceOf(Commands::class, bootstrap($os));
    }
}
