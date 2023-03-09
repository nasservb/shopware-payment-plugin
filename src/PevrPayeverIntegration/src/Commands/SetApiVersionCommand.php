<?php

/**
 * payever GmbH
 *
 * NOTICE OF LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade payever Shopware package
 * to newer versions in the future.
 *
 * @category    Payever
 * @author      payever GmbH <service@payever.de>
 * @copyright   Copyright (c) 2021 payever GmbH (http://www.payever.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Payever\PayeverPayments\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;

class SetApiVersionCommand extends Command
{
    /**
     * @var SettingsServiceInterface
     */
    private $settingsService;

    /**
     * @param SettingsServiceInterface $settingsService
     */
    public function __construct(SettingsServiceInterface $settingsService)
    {
        parent::__construct();
        $this->settingsService = $settingsService;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('payever:set_api_version')
            ->setDescription('Set API Version for Payever API')
            ->addOption(
                'salesChannelId',
                null,
                InputOption::VALUE_OPTIONAL,
                'If provided, the configuration will be set for the specified shop. Otherwise, it will be set for the default shop.' //phpcs:ignore
            )
            ->addArgument('version', InputArgument::REQUIRED, 'The version of Payever API.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $salesChannelId = $input->getOption('salesChannelId');
        $version = (string) $input->getArgument('version');

        $sio = new SymfonyStyle($input, $output);
        $this->settingsService->updateSettings(['apiVersion' => $version], $salesChannelId);

        $sio->success(sprintf('API version has been updated to "%s".', $version));

        return 0;
    }
}
