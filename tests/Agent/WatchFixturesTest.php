<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchFixtures,
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
use Innmind\Immutable\{
    Maybe,
    Sequence,
    SideEffect,
    Either,
};
use PHPUnit\Framework\TestCase;

class WatchFixturesTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchFixtures(
                $this->createMock(Protocol::class),
                $this->createMock(Filesystem::class),
                $this->createMock(IPC::class),
                Name::of('foo'),
            ),
        );
    }

    public function testSendMessageWhenSourcesAreModified()
    {
        $agent = new WatchFixtures(
            $protocol = $this->createMock(Protocol::class),
            $filesystem = $this->createMock(Filesystem::class),
            $ipc = $this->createMock(IPC::class),
            $name = Name::of('foo'),
        );
        $project = Path::of('/vendor/package/');
        $protocol
            ->expects($this->once())
            ->method('encode')
            ->with(new Activity(Type::fixturesModified))
            ->willReturn($message = $this->createMock(Message::class));
        $ipc
            ->expects($this->once())
            ->method('get')
            ->with($name)
            ->willReturn(Maybe::just($process = $this->createMock(Process::class)));
        $process
            ->expects($this->once())
            ->method('send')
            ->with(Sequence::of($message))
            ->willReturn(Maybe::just($process));
        $process
            ->expects($this->once())
            ->method('close')
            ->willReturn(Maybe::just(new SideEffect));
        $filesystem
            ->expects($this->once())
            ->method('contains')
            ->with(Path::of('/vendor/package/fixtures'))
            ->willReturn(true);
        $filesystem
            ->expects($this->once())
            ->method('watch')
            ->with(Path::of('/vendor/package/fixtures'))
            ->willReturn($ping = $this->createMock(Ping::class));
        $ping
            ->expects($this->once())
            ->method('__invoke')
            ->with($ipc, $this->callback(static function($listen) use ($ipc): bool {
                $listen($ipc); // simulate folder modification

                return true;
            }))
            ->willReturn(Either::right($ipc));

        $this->assertNull($agent($project));
    }

    public function testDoesntWatchWhenTheFolderDoesntExist()
    {
        $agent = new WatchFixtures(
            $protocol = $this->createMock(Protocol::class),
            $filesystem = $this->createMock(Filesystem::class),
            $ipc = $this->createMock(IPC::class),
            $name = Name::of('foo'),
        );
        $project = Path::of('/vendor/package/');
        $protocol
            ->expects($this->never())
            ->method('encode');
        $ipc
            ->expects($this->never())
            ->method('get');
        $filesystem
            ->expects($this->once())
            ->method('contains')
            ->with(Path::of('/vendor/package/fixtures'))
            ->willReturn(false);
        $filesystem
            ->expects($this->never())
            ->method('watch');

        $this->assertNull($agent($project));
    }
}
