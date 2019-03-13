<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation;

use Innmind\LabStation\{
    Monitor,
    Protocol,
    Trigger,
    Agent,
    Activity,
    Activity\Type,
};
use Innmind\CLI\Environment;
use Innmind\ProcessManager\Manager;
use Innmind\IPC\{
    IPC,
    Process\Name,
    Server,
    Message,
};
use Innmind\Url\Path;
use PHPUnit\Framework\TestCase;

class MonitorTest extends TestCase
{
    public function testInvokation()
    {
        $monitor = new Monitor(
            $protocol = $this->createMock(Protocol::class),
            $manager = $this->createMock(Manager::class),
            $ipc = $this->createMock(IPC::class),
            $name = new Name('monitor'),
            $trigger = $this->createMock(Trigger::class),
            $agent1 = $this->createMock(Agent::class),
            $agent2 = $this->createMock(Agent::class)
        );
        $message = $this->createMock(Message::class);
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/project'));
        $protocol
            ->expects($this->once())
            ->method('decode')
            ->with($message)
            ->willReturn($activity = new Activity(Type::sourcesModified(), []));
        $manager
            ->expects($this->once())
            ->method('schedule')
            ->with($this->callback(static function($agent): bool {
                $agent();

                return true;
            }))
            ->willReturn($manager2 = $this->createMock(Manager::class));
        $manager2
            ->expects($this->once())
            ->method('schedule')
            ->with($this->callback(static function($agent): bool {
                $agent();

                return true;
            }))
            ->willReturn($manager3 = $this->createMock(Manager::class));
        $manager3
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($manager4 = $this->createMock(Manager::class));
        $manager4
            ->expects($this->once())
            ->method('kill');
        $agent1
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Path('/project'));
        $agent2
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Path('/project'));
        $trigger
            ->expects($this->at(0))
            ->method('__invoke')
            ->with(
                new Activity(Type::start(), []),
                $env
            );
        $trigger
            ->expects($this->at(1))
            ->method('__invoke')
            ->with($activity);
        $ipc
            ->expects($this->once())
            ->method('listen')
            ->with($name)
            ->willReturn($server = $this->createMock(Server::class));
        $server
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static function($listen) use ($message): bool {
                $listen($message); // simulate an activity sent from agents

                return true;
            }));

        $this->assertNull($monitor($env));
    }
}
