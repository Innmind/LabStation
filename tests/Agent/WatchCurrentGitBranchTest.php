<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Agent;

use Innmind\LabStation\{
    Agent\WatchCurrentGitBranch,
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
use Innmind\Server\Control\Server;
use Innmind\Url\Path;
use Innmind\Git\Git;
use Innmind\TimeContinuum\{
    TimeContinuum\Earth,
    Timezone\Earth\UTC,
};
use PHPUnit\Framework\TestCase;

class WatchCurrentGitBranchTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Agent::class,
            new WatchCurrentGitBranch(
                new Git(
                    $this->createMock(Server::class),
                    new Earth(new UTC)
                ),
                $this->createMock(Protocol::class),
                $this->createMock(Watch::class),
                $this->createMock(IPC::class),
                new Name('foo')
            )
        );
    }

    public function testSendMessageWhenSourcesAreModified()
    {
        $agent = new WatchCurrentGitBranch(
            new Git(
                $server = $this->createMock(Server::class),
                new Earth(new UTC)
            ),
            $protocol = $this->createMock(Protocol::class),
            $watch = $this->createMock(Watch::class),
            $ipc = $this->createMock(IPC::class),
            $name = new Name('foo')
        );
        $project = new Path('/vendor/package');
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Server\Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "mkdir '-p' '/vendor/package'";
            }))
            ->willReturn($process = $this->createMock(Server\Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new Server\Process\ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'branch' '--no-color'";
            }))
            ->willReturn($process = $this->createMock(Server\Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new Server\Process\ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Server\Process\Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn("* develop\n  master");
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'branch' '--no-color'";
            }))
            ->willReturn($process = $this->createMock(Server\Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new Server\Process\ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Server\Process\Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn("  develop\n* master");
        $processes
            ->expects($this->at(3))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'branch' '--no-color'";
            }))
            ->willReturn($process = $this->createMock(Server\Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new Server\Process\ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Server\Process\Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn("* develop\n  master");
        $protocol
            ->expects($this->at(0))
            ->method('encode')
            ->with(new Activity(Type::gitBranchChanged(), ['branch' => 'master']))
            ->willReturn($message1 = $this->createMock(Message::class));
        $protocol
            ->expects($this->at(1))
            ->method('encode')
            ->with(new Activity(Type::gitBranchChanged(), ['branch' => 'develop']))
            ->willReturn($message2 = $this->createMock(Message::class));
        $ipc
            ->expects($this->at(0))
            ->method('get')
            ->with($name)
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('send')
            ->with($message1);
        $process
            ->expects($this->once())
            ->method('close');
        $ipc
            ->expects($this->at(1))
            ->method('get')
            ->with($name)
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('send')
            ->with($message2);
        $process
            ->expects($this->once())
            ->method('close');
        $watch
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Path('/vendor/package/.git'))
            ->willReturn($ping = $this->createMock(Ping::class));
        $ping
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static function($listen): bool {
                $listen(); // simulate folder modification
                $listen(); // simulate folder modification

                return true;
            }));

        $this->assertNull($agent($project));
    }

    public function testDoesntSendMessageWhenStillSameBranch()
    {
        $agent = new WatchCurrentGitBranch(
            new Git(
                $server = $this->createMock(Server::class),
                new Earth(new UTC)
            ),
            $protocol = $this->createMock(Protocol::class),
            $watch = $this->createMock(Watch::class),
            $ipc = $this->createMock(IPC::class),
            $name = new Name('foo')
        );
        $project = new Path('/vendor/package');
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Server\Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "mkdir '-p' '/vendor/package'";
            }))
            ->willReturn($process = $this->createMock(Server\Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new Server\Process\ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'branch' '--no-color'";
            }))
            ->willReturn($process = $this->createMock(Server\Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new Server\Process\ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Server\Process\Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn("* develop\n  master");
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'branch' '--no-color'";
            }))
            ->willReturn($process = $this->createMock(Server\Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new Server\Process\ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Server\Process\Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn("  develop\n* master");
        $processes
            ->expects($this->at(3))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'branch' '--no-color'";
            }))
            ->willReturn($process = $this->createMock(Server\Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new Server\Process\ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Server\Process\Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn("  develop\n* master");
        $protocol
            ->expects($this->once())
            ->method('encode')
            ->with(new Activity(Type::gitBranchChanged(), ['branch' => 'master']))
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
            ->with(new Path('/vendor/package/.git'))
            ->willReturn($ping = $this->createMock(Ping::class));
        $ping
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static function($listen): bool {
                $listen(); // simulate folder modification
                $listen(); // simulate folder modification

                return true;
            }));

        $this->assertNull($agent($project));
    }
}
