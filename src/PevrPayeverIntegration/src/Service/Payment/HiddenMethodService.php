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

namespace Payever\PayeverPayments\Service\Payment;

use Payever\ExternalIntegration\Payments\Enum\PaymentMethod;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Payever\PayeverPayments\HiddenMethods\HiddenMethodsEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Session\Session;
use Psr\Log\LoggerInterface;

class HiddenMethodService
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    private const SESSION_HIDDEN_METHODS_KEY = 'payever_hidden_methods';

    /**
     * @var Session
     */
    private $session;

    /**
     * @var EntityRepositoryInterface
     */
    private $hiddenMethodsRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * HiddenMethodService constructor.
     * @param Session $session
     * @param EntityRepositoryInterface $hiddenMethodsRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Session $session,
        EntityRepositoryInterface $hiddenMethodsRepository,
        LoggerInterface $logger
    ) {
        $this->session = $session;
        $this->hiddenMethodsRepository = $hiddenMethodsRepository;
        $this->logger = $logger;
    }

    /**
     * @param PaymentMethodEntity $paymentMethod
     * @param array $hiddenList
     * @return bool
     */
    public function isMethodHidden(PaymentMethodEntity $paymentMethod, array $hiddenList): bool
    {
        $methodCode = $paymentMethod->getCustomFields()[PevrPayeverIntegration::CUSTOM_FIELD_METHOD_CODE] ?? null;

        return $methodCode && isset($hiddenList[$methodCode]);
    }

    /**
     * @return array|null
     */
    public function getCurrentHiddenMethodsFromSession(): ?array
    {
        return $this->session->get(static::SESSION_HIDDEN_METHODS_KEY) ?? [];
    }

    /**
     * @param array $hiddenOptions
     */
    public function setHiddenMethodsToSession(array $hiddenOptions): void
    {
        $this->session->set(static::SESSION_HIDDEN_METHODS_KEY, $hiddenOptions);
    }

    /**
     * @param string $payeverMethodCode
     * @param string $orderId
     *
     * @throws \Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException
     */
    public function processFailedMethodByCode(string $payeverMethodCode, string $orderId): void
    {
        if (PaymentMethod::shouldHideOnReject($payeverMethodCode)) {
            $this->saveFailedMethod($payeverMethodCode, $orderId);
        }
    }

    /**
     * @param string $methodCode
     * @param string $orderId
     */
    private function saveFailedMethod(string $methodCode, string $orderId): void
    {
        $hiddenOptions = $this->getCurrentHiddenMethodsFromSession();
        $hiddenOptions[$methodCode] = time();
        $this->setHiddenMethodsToSession($hiddenOptions);
        $this->updateHiddenMethods($orderId, $hiddenOptions);
    }

    public function getCurrentHiddenMethods(string $orderId): ?HiddenMethodsEntity
    {
        return $this->hiddenMethodsRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter(HiddenMethodsEntity::FIELD_ORDER_ID, $orderId)),
            $this->getContext()
        )
            ->getEntities()
            ->first();
    }

    /**
     * @param string $orderId
     * @param array $hiddenMethods
     */
    public function updateHiddenMethods(string $orderId, array $hiddenMethods): void
    {
        try {
            $entityId = Uuid::randomHex();
            $currentHiddenMethodsEntity = $this->getCurrentHiddenMethods($orderId);
            if ($currentHiddenMethodsEntity) {
                $entityId = $currentHiddenMethodsEntity->getId();
                $currentHiddenMethods = json_decode($currentHiddenMethodsEntity->getHiddenMethods(), true);
                foreach (array_keys($currentHiddenMethods) as $methodCode) {
                    $hiddenMethods[$methodCode] = time();
                }
            }

            $this->hiddenMethodsRepository->upsert(
                [
                    [
                        HiddenMethodsEntity::FIELD_ID => $entityId,
                        HiddenMethodsEntity::FIELD_ORDER_ID => $orderId,
                        HiddenMethodsEntity::FIELD_HIDDEN_METHODS => json_encode($hiddenMethods),
                    ]
                ],
                $this->getContext()
            );
        } catch (\Exception $exception) {
            $this->logger->warning(
                $exception->getMessage(),
                [$orderId, $hiddenMethods]
            );
        }
    }
}
