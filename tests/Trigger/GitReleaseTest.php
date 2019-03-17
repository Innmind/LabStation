<?php
declare(strict_types = 1);

namespace Tests\Innmind\LabStation\Trigger;

use Innmind\LabStation\{
    Trigger\GitRelease,
    Trigger,
    Activity,
    Activity\Type,
};
use Innmind\Git\Git;
use Innmind\GitRelease\{
    Release,
    LatestVersion,
};
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\ExitCode,
    Server\Process\Output,
};
use Innmind\CLI\Environment;
use Innmind\Stream\{
    Readable,
    Writable,
};
use Innmind\Url\Path;
use Innmind\Immutable\Str;
use PHPUnit\Framework\TestCase;
use Eris\{
    Generator,
    TestTrait,
};

class GitReleaseTest extends TestCase
{
    use TestTrait;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Trigger::class,
            new GitRelease(
                new Git($this->createMock(Server::class)),
                new Release,
                new LatestVersion
            )
        );
    }

    public function testDoesntProposeReleaseWhenNotBranchChangeActivity()
    {
        $trigger = new GitRelease(
            new Git(
                $server = $this->createMock(Server::class)
            ),
            new Release,
            new LatestVersion
        );
        $server
            ->expects($this->never())
            ->method('processes');

        $this
            ->forAll(Generator\elements(
                'sourcesModified',
                'testsModified',
                'start'
            ))
            ->then(function(string $type) use ($trigger): void {
                $this->assertNull($trigger(
                    new Activity(Type::{$type}(), []),
                    $this->createMock(Environment::class)
                ));
            });
    }

    public function testDoesntProposeReleaseWhenNotMasterBranch()
    {
        $trigger = new GitRelease(
            new Git(
                $server = $this->createMock(Server::class)
            ),
            new Release,
            new LatestVersion
        );
        $server
            ->expects($this->never())
            ->method('processes');

        $this
            ->forAll(Generator\string())
            ->then(function(string $branch) use ($trigger): void {
                $this->assertNull($trigger(
                    new Activity(Type::gitBranchChanged(), ['branch' => $branch]),
                    $this->createMock(Environment::class)
                ));
            });
    }

    public function testDoesntReleaseWhenNoKindChosen()
    {
        $trigger = new GitRelease(
            new Git(
                $server = $this->createMock(Server::class)
            ),
            new Release,
            new LatestVersion
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "mkdir '-p' '/vendor/package'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn('1.0.0|||foo');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/vendor/package'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->at(0))
            ->method('write')
            ->with(Str::of("1.0.0\n"));
        $output
            ->expects($this->at(1))
            ->method('write')
            ->with(Str::of("Kind of release:\n"));
        $output
            ->expects($this->at(2))
            ->method('write')
            ->with(Str::of("[1] major\n"));
        $output
            ->expects($this->at(3))
            ->method('write')
            ->with(Str::of("[2] minor\n"));
        $output
            ->expects($this->at(4))
            ->method('write')
            ->with(Str::of("[3] bugfix\n"));
        $output
            ->expects($this->at(5))
            ->method('write')
            ->with(Str::of("[4] none\n"));
        $output
            ->expects($this->at(6))
            ->method('write')
            ->with(Str::of('> '));

        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));

        $this->assertNull($trigger(
            new Activity(Type::gitBranchChanged(), ['branch' => 'master']),
            $env
        ));
    }

    public function testDoesntReleaseWhenChoseToNotRelease()
    {
        $trigger = new GitRelease(
            new Git(
                $server = $this->createMock(Server::class)
            ),
            new Release,
            new LatestVersion
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "mkdir '-p' '/vendor/package'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn('1.0.0|||foo');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/vendor/package'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->at(0))
            ->method('write')
            ->with(Str::of("1.0.0\n"));
        $output
            ->expects($this->at(1))
            ->method('write')
            ->with(Str::of("Kind of release:\n"));
        $output
            ->expects($this->at(2))
            ->method('write')
            ->with(Str::of("[1] major\n"));
        $output
            ->expects($this->at(3))
            ->method('write')
            ->with(Str::of("[2] minor\n"));
        $output
            ->expects($this->at(4))
            ->method('write')
            ->with(Str::of("[3] bugfix\n"));
        $output
            ->expects($this->at(5))
            ->method('write')
            ->with(Str::of("[4] none\n"));
        $output
            ->expects($this->at(6))
            ->method('write')
            ->with(Str::of('> '));

        $input = \fopen('php://temp', 'r+');
        \fwrite($input, "4\n");
        $env
            ->expects($this->once())
            ->method('input')
            ->willReturn(new Readable\Stream($input));

        $this->assertNull($trigger(
            new Activity(Type::gitBranchChanged(), ['branch' => 'master']),
            $env
        ));
    }

    public function testDoesntReleaseWhenEmptyMessage()
    {
        $trigger = new GitRelease(
            new Git(
                $server = $this->createMock(Server::class)
            ),
            new Release,
            new LatestVersion
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "mkdir '-p' '/vendor/package'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn('1.0.0|||foo');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/vendor/package'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->at(0))
            ->method('write')
            ->with(Str::of("1.0.0\n"));
        $output
            ->expects($this->at(1))
            ->method('write')
            ->with(Str::of("Kind of release:\n"));
        $output
            ->expects($this->at(2))
            ->method('write')
            ->with(Str::of("[1] major\n"));
        $output
            ->expects($this->at(3))
            ->method('write')
            ->with(Str::of("[2] minor\n"));
        $output
            ->expects($this->at(4))
            ->method('write')
            ->with(Str::of("[3] bugfix\n"));
        $output
            ->expects($this->at(5))
            ->method('write')
            ->with(Str::of("[4] none\n"));
        $output
            ->expects($this->at(6))
            ->method('write')
            ->with(Str::of('> '));
        $output
            ->expects($this->at(7))
            ->method('write')
            ->with(Str::of('message: '));
        $firstInput = \fopen('php://temp', 'r+');
        \fwrite($firstInput, "1\n");
        $secondInput = \fopen('php://temp', 'r+');
        \fwrite($secondInput, "\n");
        $env
            ->expects($this->exactly(2))
            ->method('input')
            ->will($this->onConsecutiveCalls(
                new Readable\Stream($firstInput),
                new Readable\Stream($secondInput)
            ));

        $this->assertNull($trigger(
            new Activity(Type::gitBranchChanged(), ['branch' => 'master']),
            $env
        ));
    }

    /**
     * @dataProvider kinds
     */
    public function testRelease(int $kind, string $expected)
    {
        $trigger = new GitRelease(
            new Git(
                $server = $this->createMock(Server::class)
            ),
            new Release,
            new LatestVersion
        );
        $server
            ->expects($this->any())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "mkdir '-p' '/vendor/package'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'tag' '--list' '--format=%(refname:strip=2)|||%(subject)'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn($output = $this->createMock(Output::class));
        $output
            ->expects($this->once())
            ->method('__toString')
            ->willReturn('1.0.0|||foo');
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command) use ($expected): bool {
                return (string) $command === "git 'tag' '-s' '-a' '$expected' '-m' 'foo'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(3))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'push'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $processes
            ->expects($this->at(4))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "git 'push' '--tags'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('workingDirectory')
            ->willReturn(new Path('/vendor/package'));
        $env
            ->expects($this->any())
            ->method('output')
            ->willReturn($output = $this->createMock(Writable::class));
        $output
            ->expects($this->at(0))
            ->method('write')
            ->with(Str::of("1.0.0\n"));
        $output
            ->expects($this->at(1))
            ->method('write')
            ->with(Str::of("Kind of release:\n"));
        $output
            ->expects($this->at(2))
            ->method('write')
            ->with(Str::of("[1] major\n"));
        $output
            ->expects($this->at(3))
            ->method('write')
            ->with(Str::of("[2] minor\n"));
        $output
            ->expects($this->at(4))
            ->method('write')
            ->with(Str::of("[3] bugfix\n"));
        $output
            ->expects($this->at(5))
            ->method('write')
            ->with(Str::of("[4] none\n"));
        $output
            ->expects($this->at(6))
            ->method('write')
            ->with(Str::of('> '));
        $output
            ->expects($this->at(7))
            ->method('write')
            ->with(Str::of('message: '));
        $firstInput = \fopen('php://temp', 'r+');
        \fwrite($firstInput, "$kind\n");
        $secondInput = \fopen('php://temp', 'r+');
        \fwrite($secondInput, "foo\n");
        $env
            ->expects($this->exactly(2))
            ->method('input')
            ->will($this->onConsecutiveCalls(
                new Readable\Stream($firstInput),
                new Readable\Stream($secondInput)
            ));

        $this->assertNull($trigger(
            new Activity(Type::gitBranchChanged(), ['branch' => 'master']),
            $env
        ));
    }

    public function kinds(): array
    {
        return [
            [1, '2.0.0'],
            [2, '1.1.0'],
            [3, '1.0.1'],
        ];
    }
}
