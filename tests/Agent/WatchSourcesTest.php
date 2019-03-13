<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchSources,
    Agent,
    Protocol,
    Activity,
    Activity\Type,
};
use Innmind\FileWatch\{
    Watch,
    Ping,
};
use Innmind\IPC\{
    IPC,
    Message,
    Process,
    Process\Name,
};
use Innmind\Url\Path;
use PHPUnit\Framework\TestCase;

class WatchSourcesTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchSources(
                $this->createMock(Protocol::class),
                $this->createMock(Watch::class),
                $this->createMock(IPC::class),
                new Name('foo')
            )
        );
    }

    public function testSendMessageWhenSourcesAreModified()
    {
        $agent = new WatchSources(
            $protocol = $this->createMock(Protocol::class),
            $watch = $this->createMock(Watch::class),
            $ipc = $this->createMock(IPC::class),
            $name = new Name('foo')
        );
        $project = new Path('/vendor/package');
        $protocol
            ->expects($this->once())
            ->method('encode')
            ->with(new Activity(Type::sourcesModified(), []))
            ->willReturn($message = $this->createMock(Message::class));
        $ipc
            ->expects($this->once())
            ->method('get')
            ->with($name)
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('send')
            ->with($message);
        $process
            ->expects($this->once())
            ->method('close');
        $watch
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Path('/vendor/package/src'))
            ->willReturn($ping = $this->createMock(Ping::class));
        $ping
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static function($listen): bool {
                $listen(); // simulate folder modification

                return true;
            }));

        $this->assertNull($agent($project));
    }
}