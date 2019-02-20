<?php
declare(strict_types=1);

namespace PHPSu\Tests\Process;

use PHPSu\Process\Process;
use PHPSu\Process\ProcessManager;
use PHPUnit\Framework\TestCase;

final class ProcessManagerTest extends TestCase
{
    public function testProcessesShouldBeRunning(): void
    {
        $processManager = new ProcessManager();
        $processManager->addProcess($pList1 = Process::fromShellCommandline('echo "Testing List1" && sleep 0.1')->setName('list1'));
        $processManager->addProcess($pList2 = Process::fromShellCommandline('echo "Testing List2" && sleep 0.1')->setName('list2'));
        $processManager->start();
        $this->assertTrue($pList1->isRunning(), 'Process1 should be running');
        $this->assertTrue($pList2->isRunning(), 'Process2 should be running');
        $processManager->wait();
        $this->assertNotTrue($pList1->isRunning(), 'Process1 shouldn\'t be running');
        $this->assertNotTrue($pList2->isRunning(), 'Process2 shouldn\'t be running');
        $this->assertSame('Testing List1' . PHP_EOL, $pList1->getOutput());
        $this->assertSame('Testing List2' . PHP_EOL, $pList2->getOutput());
    }

    /**
     * @expectedException \Exception
     */
    public function testRunWithError(): void
    {
        (new ProcessManager())
            ->addProcess($pError = Process::fromShellCommandline('error')->setName('error' . md5(random_bytes(100))))
            ->start()
            ->wait()
            ->validateProcesses();
    }

    public function testRunGetErrorOutput(): void
    {
        $processManager = new ProcessManager();
        $name = 'error' . md5(random_bytes(100));
        $processManager->addProcess($pError = Process::fromShellCommandline('error')->setName($name));
        $processManager->addProcess($pList1 = Process::fromShellCommandline('echo "Testing List1"')->setName('list1'));
        $processManager->start();
        try {
            $processManager->wait()->validateProcesses();
        } catch (\Exception $exception) {
            $this->assertSame([$name => $pError->getErrorOutput()], $processManager->getErrorOutputs());
            return;
        }
        $this->assertTrue(false, 'Exception should be thrown');
    }

    public function testAddOutputCallback(): void
    {
        $processManager = new ProcessManager();
        $processManager->addOutputCallback(function () {
            return true;
        });
        $property = (new \ReflectionClass($processManager))->getProperty('outputCallbacks');
        $property->setAccessible(true);
        foreach ($property->getValue($processManager) as $callback) {
            $this->assertTrue($callback());
        }
    }

    public function testAddStateChangeCallback(): void
    {
        $processManager = new ProcessManager();
        $processManager->addStateChangeCallback(function () {
            return true;
        });
        $property = (new \ReflectionClass($processManager))->getProperty('stateChangeCallbacks');
        $property->setAccessible(true);
        foreach ($property->getValue($processManager) as $callback) {
            $this->assertTrue($callback());
        }
    }

    public function testAddTickCallback(): void
    {
        $processManager = new ProcessManager();
        $processManager->addTickCallback(function () {
            return true;
        });
        $property = (new \ReflectionClass($processManager))->getProperty('tickCallbacks');
        $property->setAccessible(true);
        foreach ($property->getValue($processManager) as $callback) {
            $this->assertTrue($callback());
        }
    }

    public function testProcessManagerMustRun(): void
    {
        $result = (new ProcessManager())
            ->addProcess($pError = Process::fromShellCommandline('echo hi'))
            ->mustRun();
        $this->assertContains($result->getState(0), [Process::STATE_RUNNING, Process::STATE_SUCCEEDED]);
    }
}
