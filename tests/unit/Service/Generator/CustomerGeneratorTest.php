<?php

namespace Payever\PayeverPayments\tests\unit\Service\Generator;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ResultStatement;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\AddressEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\PaymentDetailsEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\Generator\CustomerGenerator;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomerGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityWriterInterface
     */
    private $writer;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var MockObject|NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    /**
     * @var MockObject|CustomerDefinition
     */
    private $customerDefinition;

    /**
     * @var MockObject|ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @var CustomerGenerator
     */
    private $generator;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->writer = $this->getMockBuilder(EntityWriterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->numberRangeValueGenerator = $this->getMockBuilder(NumberRangeValueGeneratorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerDefinition = $this->getMockBuilder(CustomerDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->connectionHelper = $this->getMockBuilder(ConnectionHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->generator = new CustomerGenerator(
            $this->writer,
            $this->customerRepository,
            $this->numberRangeValueGenerator,
            $this->customerDefinition,
            $this->connectionHelper
        );
    }

    public function testGenerate()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $paymentResult */
        $paymentResult = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getAddress', 'getPaymentDetails'])
            ->getMock();
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentResult->expects($this->once())
            ->method('getAddress')
            ->willReturn(
                $this->getMockBuilder(AddressEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->connectionHelper->expects($this->any())
            ->method('executeQuery')
            ->willReturn(
                $this->getMockBuilder(
                    class_exists('Doctrine\DBAL\Driver\Result')
                        ? Result::class
                        : ResultStatement::class
                )
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->connectionHelper->expects($this->any())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => hex2bin('f5de4960641b454f9fd87779c3115216'),
                    'salutation_key' => 'mr',
                    'iso' => 'DE',
                ]
            ]);
        $this->connectionHelper->expects($this->once())
            ->method('fetchOne')
            ->willReturn(hex2bin('703034f53ca24e418669470fc1e4650d'));
        $paymentResult->expects($this->once())
            ->method('getPaymentDetails')
            ->willReturn(
                $paymentDetails = $this->getMockBuilder(PaymentDetailsEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentDetails->expects($this->once())
            ->method('__call')
            ->willReturn(new \DateTime());
        $this->writer->expects($this->once())
            ->method('upsert');
        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(CustomerEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->generator->generate($context, $paymentResult);
    }
}
