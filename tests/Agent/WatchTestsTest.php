<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchTests,
    Agent,
    Protocol,
    Activity,
    Activity\Type,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\FileWatch\Ping;
use Innmind\IPC\{
    IPC,
    Message,
    Process,
    Process\Name,
};
use Innmind\Url\Path;
use PHPUnit\Framework\TestCase;

class WatchTestsTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchTests(
                $this->createMock(Protocol::class),
                $this->createMock(Filesystem::class),
                $this->createMock(IPC::class),
                new Name('foo'),
            ),
        );
    }

    public function testSendMessageWhenSourcesAreModified()
    {
        $agent = new WatchTests(
            $protocol = $this->createMock(Protocol::class),
            $filesystem = $this->createMock(Filesystem::class),
            $ipc = $this->createMock(IPC::class),
            $name = new Name('foo'),
        );
        $project = Path::of('/vendor/package/');
        $protocol
            ->expects($this->once())
            ->method('encode')
            ->with(new Activity(Type::testsModified))
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
        $filesystem
            ->expects($this->once())
            ->method('watch')
            ->with(Path::of('/vendor/package/tests'))
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
