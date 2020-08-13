<?php

declare(strict_types=1);

namespace PHPSu\Tests\Cli;

use PHPSu\Cli\MysqlCliCommand;
use PHPSu\Cli\SshCliCommand;
use PHPSu\Config\ConfigurationLoaderInterface;
use PHPSu\Config\GlobalConfig;
use PHPSu\Controller;
use PHPSu\ShellCommandBuilder\ShellBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class MysqlCliCommandTest
 * @package PHPSu\Tests\Cli
 */
final class MysqlCliCommandTest extends TestCase
{
    public function testMysqlCliCommandDryRunMultipleCommands(): void
    {
        $mockConfigurationLoader = $this->createMockConfigurationLoader($this->createConfig());

        $command = new MysqlCliCommand($mockConfigurationLoader, new Controller());
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'instance' => 'production',
            '--dry-run' => true,
        ]);

        $compareWith = ShellBuilder::command('ssh')
            ->addShortOption('t')
            ->addShortOption('F', '.phpsu/config/ssh_config')
            ->addArgument('us')
            ->addArgument(
                ShellBuilder::command('mysql')
                ->addOption('user', 'n', true, true)
                ->addOption('password', 'c', true, true)
                ->addOption('host', 'd', false, true)
                ->addOption('port', '3306', false, true)
                ->addArgument('a')
            );

        $output = $commandTester->getDisplay();
        $this->assertSame((string)$compareWith, trim($output));
        $this->assertSame(0, $commandTester->getStatusCode());
    }

    public function testMysqlCliCommandDryRunInteractive(): void
    {
        $mockConfigurationLoader = $this->createMockConfigurationLoader($this->createConfig());

        $command = new MysqlCliCommand($mockConfigurationLoader, new Controller());
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['production']);
        $commandTester->execute([
            '--dry-run' => true,
        ]);

        $compareWith = ShellBuilder::command('ssh')
            ->addShortOption('t')
            ->addShortOption('F', '.phpsu/config/ssh_config')
            ->addArgument('us')
            ->addArgument(
                ShellBuilder::command('mysql')
                    ->addOption('user', 'n', true, true)
                    ->addOption('password', 'c', true, true)
                    ->addOption('host', 'd', false, true)
                    ->addOption('port', '3306', false, true)
                    ->addArgument('a')
            );

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Please select one of the AppInstances', $output);
        $this->assertStringContainsString('You selected: production', $output);
        $this->assertStringContainsString((string)$compareWith, $output);
        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * @return GlobalConfig
     */
    private function createConfig(): GlobalConfig
    {
        $globalConfig = new GlobalConfig();
        $globalConfig->addSshConnection('us', 'ssh://user@us');
        $globalConfig->addAppInstance('production', 'us', '/var/www/')
            ->addDatabase('test', 'a', 'n', 'c', 'd');
        return $globalConfig;
    }

    /**
     * @param GlobalConfig $config
     * @return ConfigurationLoaderInterface|MockObject
     */
    private function createMockConfigurationLoader(GlobalConfig $config)
    {
        /** @var MockObject|ConfigurationLoaderInterface $mockConfigurationLoader */
        $mockConfigurationLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $mockConfigurationLoader->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn($config);
        return $mockConfigurationLoader;
    }
}
