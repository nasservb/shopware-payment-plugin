<?php

namespace Payever\PayeverPayments\tests\unit\Commands;

use PHPUnit\Framework\MockObject\MockObject;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use Payever\PayeverPayments\Commands\SetApiVersionCommand;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetApiVersionCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|SettingsServiceInterface
     */
    private $settingsService;

    /**
     * @var SetApiVersionCommand
     */
    private $command;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        /** @var MockObject|SettingsServiceInterface $transaction */
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->command = new SetApiVersionCommand($this->settingsService);
    }

    public function testUpdateSettings()
    {
        $input = $this->getMockBuilder(InputInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $output = $this->getMockBuilder(OutputInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $output->expects($this->any())
            ->method('getFormatter')
            ->willReturn(
                $formatter = $this->getMockBuilder(OutputFormatterInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $output->expects($this->any())
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_NORMAL);

        $formatter->expects($this->any())
            ->method('isDecorated')
            ->willReturn(false);

        $this->settingsService->expects($this->once())
            ->method('updateSettings');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('execute');
        $method->setAccessible(true);
        $method->invoke($this->command, $input, $output);
    }
}
