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

namespace Payever\PayeverPayments\Service\Generator;

use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\CustomerHelper;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @see \Shopware\Core\Framework\Demodata\Generator\CustomerGenerator
 */
class CustomerGenerator
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    /**
     * @var CustomerDefinition
     */
    private $customerDefinition;

    /**
     * @var ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @var array
     */
    private $salutations;

    /**
     * @var array
     */
    private $countries;

    /**
     * @param EntityWriterInterface $writer
     * @param EntityRepositoryInterface $customerRepository
     * @param NumberRangeValueGeneratorInterface $numberRangeValueGenerator
     * @param CustomerDefinition $customerDefinition
     * @param ConnectionHelper $connectionHelper
     */
    public function __construct(
        EntityWriterInterface $writer,
        EntityRepositoryInterface $customerRepository,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        CustomerDefinition $customerDefinition,
        ConnectionHelper $connectionHelper
    ) {
        $this->writer = $writer;
        $this->customerRepository = $customerRepository;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->customerDefinition = $customerDefinition;
        $this->connectionHelper = $connectionHelper;
    }

    /**
     * @param SalesChannelContext $context
     * @param RetrievePaymentResultEntity $paymentResult
     * @return CustomerEntity
     * @throws \Doctrine\DBAL\DBALException
     */
    public function generate(SalesChannelContext $context, RetrievePaymentResultEntity $paymentResult): CustomerEntity
    {
        $writeContext = WriteContext::createFromContext($context->getContext());
        $payload = [];
        $address = $paymentResult->getAddress();
        $customerId = Uuid::randomHex();
        $firstName = $address->getFirstName();
        $lastName = $address->getLastName();
        $salutationId = $this->getSalutationId((string) $address->getSalutation());
        $title = '';
        $countryId = $this->getCountryId((string) $address->getCountry());
        $addressId = Uuid::randomHex();
        $addresses = [
            [
                'id' => $addressId,
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'title' => $title,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'street' => $address->getStreet() . ' ' . $address->getStreetNumber(),
                'zipcode' => $address->getZipCode(),
                'city' => $address->getCity(),
                'phoneNumber' => $address->getPhone(),
            ]
        ];
        $customer = [
            'id' => $customerId,
            'customerNumber' => $this->numberRangeValueGenerator->getValue('customer', $context->getContext(), null),
            'salutationId' => $salutationId,
            'title' => $title,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $address->getEmail(),
            'password' => Uuid::randomHex(),
            'defaultPaymentMethodId' => $this->getDefaultPaymentMethod(),
            'guest' => true,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => $addresses,
            'customFields' => [CustomerHelper::CUSTOM_FIELD_TRANSACTION_IDS => [$paymentResult->getId()]],
        ];
        $birthday = $paymentResult->getPaymentDetails()->getBirthday();
        if ($birthday instanceof \DateTime) {
            $customer['birthday'] = $birthday->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }
        $payload[] = $customer;
        $this->writer->upsert($this->customerDefinition, $payload, $writeContext);
        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context->getContext())->first();

        return $customer;
    }

    /**
     * @param string $salutation
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getSalutationId(string $salutation): string
    {
        if (null === $this->salutations) {
            $statement = $this->connectionHelper->executeQuery('SELECT id, salutation_key FROM salutation');
            $rows = $this->connectionHelper->fetchAllAssociative($statement);
            foreach ($rows as $row) {
                $this->salutations[Uuid::fromBytesToHex($row['id'])] = $row['salutation_key'];
            }
        }
        $result = (string) array_key_first($this->salutations);
        foreach ($this->salutations as $id => $salutationKey) {
            if ($salutationKey === $salutation) {
                $result = $id;
                break;
            }
        }

        return $result;
    }

    /**
     * @param string $isoCode
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getCountryId(string $isoCode): string
    {
        if (null === $this->countries) {
            $statement = $this->connectionHelper->executeQuery('SELECT id, iso FROM country WHERE active = 1');
            $rows = $this->connectionHelper->fetchAllAssociative($statement);
            foreach ($rows as $row) {
                $this->countries[Uuid::fromBytesToHex($row['id'])] = strtolower($row['iso']);
            }
        }
        $result = (string) array_key_first($this->countries);
        foreach ($this->countries as $countryId => $iso) {
            if (strtolower($isoCode) === $iso) {
                $result = $countryId;
                break;
            }
        }

        return $result;
    }

    /**
     * @return string|null
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getDefaultPaymentMethod(): ?string
    {
        $statement = $this->connectionHelper->executeQuery(
            'SELECT `id` FROM `payment_method` WHERE `active` = 1 ORDER BY `position`'
        );
        $paymentMethodId = $this->connectionHelper->fetchOne($statement);
        if (!$paymentMethodId) {
            return null;
        }

        return Uuid::fromBytesToHex($paymentMethodId);
    }
}
