<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation;

use function Innmind\LabStation\bootstrap;
use Innmind\OperatingSystem\{
    OperatingSystem,
    CurrentProcess,
};
use Innmind\Server\Control\Server\Process\Pid;
use Innmind\Server\Status\Server;
use Innmind\CLI\Commands;
use Innmind\Url\Path;
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
        $os
            ->expects($this->any())
            ->method('status')
            ->willReturn($server = $this->createMock(Server::class));
        $server
            ->expects($this->any())
            ->method('tmp')
            ->willReturn(Path::none());

        $this->assertInstanceOf(Commands::class, bootstrap($os));
    }
}
