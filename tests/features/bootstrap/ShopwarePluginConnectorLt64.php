<?php

namespace Payever\Tests;

use Assert\Assertion;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use PackageVersions\Versions;
use Payever\PayeverPayments\ScheduledTask\SynchronizationQueueTaskHandler;
use Payever\PayeverPayments\Service\Management\CategoryManager;
use Payever\PayeverPayments\Service\Management\OptionManager;
use Payever\PayeverPayments\Service\Management\PriceManager;
use Payever\PayeverPayments\Service\Management\SubscriptionManager;
use Payever\PayeverPayments\Service\PayeverRegistry;
use Payever\PayeverPayments\Service\Payment\PaymentOptionsService;
use Payever\PayeverPayments\Service\Setting\SettingsService;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use Payever\Stub\BehatExtension\ServiceContainer\PluginConnectorInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Development\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

class ShopwarePluginConnectorLt64 implements PluginConnectorInterface
{
    public const PLUGIN_CODE = 'PevrPayeverIntegration';
    public const STUB_PRODUCT_GROSS_PRICE = 300;
    private const KERNEL_ENV = 'behat';
    private const CONFIG_KEY_AUTH_TOKEN = 'PevrPayeverIntegration.config.oauthToken';
    private const CONFIG_KEY_IS_SANDBOX = 'PevrPayeverIntegration.config.isSandbox';
    private const CONFIG_KEY_SANDBOX_URL = 'PevrPayeverIntegration.config.sandboxUrl';
    private const CONFIG_KEY_LIVE_URL = 'PevrPayeverIntegration.config.liveUrl';
    private const CONFIG_KEY_THIRD_PARTY_SANDBOX_URL = 'PevrPayeverIntegration.config.thirdPartyProductsSandboxUrl';
    private const CONFIG_KEY_THIRD_PARTY_LIVE_URL = 'PevrPayeverIntegration.config.thirdPartyProductsLiveUrl';
    private const CONFIG_KEY_CLIENT_ID = 'PevrPayeverIntegration.config.clientId';
    private const CONFIG_KEY_CLIENT_SECRET = 'PevrPayeverIntegration.config.clientSecret';
    private const CONFIG_KEY_BUSINESS_UUID = 'PevrPayeverIntegration.config.businessUuid';
    private const CONFIG_KEY_IS_IFRAME = 'PevrPayeverIntegration.config.isIframe';
    private const CONFIG_KEY_PRODUCTS_SYNC_ENABLED = 'PevrPayeverIntegration.config.isProductsSyncEnabled';
    private const CONFIG_KEY_PRODUCTS_SYNC_OUTWARD_ENABLED = 'PevrPayeverIntegration.config.isProductsOutwardSyncEnabled';
    private const CONFIG_KEY_PRODUCTS_SYNC_EXTERNAL_ID = 'PevrPayeverIntegration.config.productsSyncExternalId';

    /** @var string */
    private $shopwareDir;

    /** @var \PDO */
    private $pdo;

    /** @var Kernel */
    private $shopwareKernel;

    /** @var Connection */
    private $connection;

    /** @var RouterInterface|null */
    private $router;

    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var EntityRepositoryInterface */
    private $systemConfigRepository;

    /** @var EntityRepositoryInterface */
    private $productRepository;

    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;

    /** @var EntityRepositoryInterface */
    private $synchronizationQueueRepository;

    /** @var SubscriptionManager */
    private $subscriptionManager;

    /** @var PriceManager */
    private $priceManager;

    /** @var CategoryManager */
    private $categoryManager;

    /** @var OptionManager */
    private $optionManager;

    /** @var SynchronizationQueueTaskHandler */
    private $synchronizationQueueTaskHandler;

    /** @var SettingsService|null */
    private $settingsService;

    /** @var PaymentOptionsService */
    private $paymentOptionsService;

    /** @var ProductTransformer */
    private $productTransformer;

    /** @var string */
    private $shopwareVersion;

    /**
     * @param string $shopwareDir
     *
     * @throws \RuntimeException
     */
    public function __construct(string $shopwareDir)
    {
        if (!file_exists($shopwareDir)) {
            throw new \RuntimeException(sprintf('Shopware directory %s does not exists', $shopwareDir));
        }
        $this->shopwareDir = rtrim($shopwareDir, '/');
        $config = $this->getApplicationConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $config['const']['DB_HOST'],
            $config['const']['DB_PORT'],
            $config['const']['DB_NAME']
        );
        $this->pdo = new \PDO(
            $dsn,
            $config['const']['DB_USER'],
            $config['const']['DB_PASSWORD'],
            [
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
        $this->initShopwareKernel($config);

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if (file_exists($shopwareDir . '/vendor/shopware/core/Framework/Uuid/Uuid.php')) {
            require_once $shopwareDir . '/vendor/shopware/core/Framework/Uuid/Uuid.php';
        }
    }

    /**
     * Initializes shopware kernel
     * @param array $config
     */
    protected function initShopwareKernel(array $config): void
    {
        $envVars = [
            'DATABASE_URL' => sprintf(
                'mysql://%s:%s@%s:%s/%s',
                $config['const']['DB_USER'],
                $config['const']['DB_PASSWORD'],
                $config['const']['DB_HOST'],
                $config['const']['DB_PORT'],
                $config['const']['DB_NAME']
            ),
            'SHOPWARE_ES_HOSTS' => 'elasticsearch:9200',
            'SHOPWARE_ES_ENABLED' => '0',
            'SHOPWARE_ES_INDEXING_ENABLED' => '0',
            'SHOPWARE_ES_INDEX_PREFIX' => 'sw',
            'APP_SECRET' => 'test',
            'APP_URL' => $config['const']['APP_URL'],
        ];
        if (empty($_SERVER['APP_URL'])) {
            $_SERVER['APP_URL'] = $config['const']['APP_URL'];
        }
        foreach ($envVars as $var => $val) {
            \putenv(sprintf('%s=%s', $var, $val));
        }
        // trigger load conflict classes before shopware autoload
        class_exists('GuzzleHttp\Client');
        echo   '---------------------------'.
       $this->shopwareDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR
            . 'autoload.php-------------------------------';


        $classLoader = require $this->shopwareDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR
            . 'autoload.php';
        $connection = $this->getConnection();
        $shopwareVersionRevision = null;
        try {
            $shopwareVersionRevision = Versions::getVersion('shopware/platform');
            [$version, $hash] = explode('@', $shopwareVersionRevision);
            $version = ltrim($version, 'v');
            $this->shopwareVersion = (string) str_replace('+', '-', $version);
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
        $pluginLoader = new DbalKernelPluginLoader($classLoader, null, $connection);
        $cacheId = $this->getCacheId($connection);
        $this->shopwareKernel = new Kernel(
            self::KERNEL_ENV,
            false,
            $pluginLoader,
            $cacheId,
            $shopwareVersionRevision,
            $connection
        );
        $this->shopwareKernel->boot();
        if ($router = $this->getRouter()) {
            $host = str_replace(
                ['http://', 'https://'],
                '',
                $config['const']['APP_URL']
            );
            $router->setContext(new RequestContext('', 'GET', $host));
        }
    }

    /**
     * @param Connection $connection
     * @return string
     */
    protected function getCacheId(Connection $connection): string
    {
        $cacheId = null;
        try {
            $cacheId = $connection->fetchColumn(
                'SELECT `value` FROM app_config WHERE `key` = :key',
                ['key' => 'cache-id']
            );
        } catch (\Exception $e) {
        }

        return $cacheId ?? Uuid::randomHex();
    }

    /**
     * {@inheritDoc}
     */
    public function prepareCmsConfig(): void
    {
        $this->setupStubProduct();
        $this->setupCurrenciesAndRates();
        $this->configureEmailTemplates();
    }

    /**
     * {@inheritDoc}
     */
    public function getPluginDefaultConfig(): array
    {
        return [
            self::CONFIG_KEY_IS_SANDBOX => true,
            self::CONFIG_KEY_IS_IFRAME => false,
            self::CONFIG_KEY_CLIENT_ID => '1454_2ax8i5chkvggc8w00g8g4sk80ckswkw0c8k8scss40o40ok4sk',
            self::CONFIG_KEY_CLIENT_SECRET => '22uvxi05qlgk0wo8ws8s44wo8ccg48kwogoogsog4kg4s8k8k',
            self::CONFIG_KEY_BUSINESS_UUID => 'payever',
            self::CONFIG_KEY_PRODUCTS_SYNC_ENABLED => false,
            self::CONFIG_KEY_PRODUCTS_SYNC_OUTWARD_ENABLED => true,
            self::CONFIG_KEY_PRODUCTS_SYNC_EXTERNAL_ID => 'externalIdHash',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function isPluginEnabled(): bool
    {
        shell_exec(sprintf('cd %s && php ./bin/console plugin:refresh', $this->shopwareDir));
        $list = shell_exec(
            sprintf(
                'cd %s && php ./bin/console plugin:list | grep %s',
                $this->shopwareDir,
                static::PLUGIN_CODE
            )
        );
        $list = trim($list);
        if (empty($list)) {
            return false;
        }
        $list = preg_replace('/\s+/', ' ', $list);

        return strpos($list, 'payever GmbH Yes Yes') !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function enablePlugin(): void
    {
        if ($this->isPluginEnabled()) {
            return;
        }
        $install = shell_exec(sprintf(
            'cd %s && php ./bin/console plugin:install %s',
            $this->shopwareDir,
            static::PLUGIN_CODE
        ));
        Assertion::contains($install, 'Installed 1 plugin', sprintf('Can not install the plugin: %s', $install));
        $active = shell_exec(sprintf(
            'cd %s && php ./bin/console plugin:activate --clearCache %s',
            $this->shopwareDir,
            static::PLUGIN_CODE
        ));
        Assertion::contains($active, 'Activated 1 plugin', sprintf('Can not activate the plugin: %s', $active));
        $this->clearEnqueue();
        $this->clearCache();
    }

    /**
     * {@inheritDoc}
     */
    public function disablePlugin(): void
    {
        if (!$this->isPluginEnabled()) {
            return;
        }
        $deactivate = shell_exec(sprintf(
            'cd %s && php ./bin/console plugin:deactivate %s',
            $this->shopwareDir,
            static::PLUGIN_CODE
        ));
        Assertion::contains($deactivate, 'Deactivated 1 plugin', 'Can not deactivate plugin');
        $uninstall = shell_exec(sprintf(
            'cd %s && php ./bin/console plugin:uninstall --clearCache %s',
            $this->shopwareDir,
            static::PLUGIN_CODE
        ));
        Assertion::contains($uninstall, 'Uninstalled 1 plugin', 'Can not uninstall plugin');
        $this->clearCache();
    }

    /**
     * {@inheritDoc}
     */
    public function setPluginConfigValue($key, $value): void
    {
        $this->getSystemConfigService()->set($key, $value);
        $this->clearConfigCache();
    }

    /**
     * Clears config cache
     */
    public function clearConfigCache(): void
    {
        if ($this->getSettingsService()) {
            $this->getSettingsService()->resetCache();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPluginConfigValue($key)
    {
        // @see SystemConfigService::getDomain()
        $domain = 'PevrPayeverIntegration.config.';
        $queryBuilder = $this->getConnection()->createQueryBuilder()
            ->select('LOWER(HEX(id))')
            ->from('system_config')
            ->where('sales_channel_id IS NULL');
        $domain = rtrim($domain, '.') . '.';
        $escapedDomain = str_replace('%', '\\%', $domain);
        $queryBuilder->andWhere('configuration_key LIKE :prefix')
            ->orderBy('configuration_key', 'ASC')
            ->addOrderBy('sales_channel_id', 'ASC')
            ->setParameter('prefix', $escapedDomain . '%')
            ->setParameter('salesChannelId', null);
        $ids = $queryBuilder->execute()->fetchAll(FetchMode::COLUMN);
        $data = [];
        if (!empty($ids)) {
            $criteria = new Criteria($ids);
            /** @var SystemConfigCollection $collection */
            $collection = $this->getSystemConfigRepository()
                ->search($criteria, $this->getDefaultContext())
                ->getEntities();
            $collection->sortByIdArray($ids);
            foreach ($collection as $cur) {
                // use the last one with the same key.
                // entities with sales_channel_id === null are sorted before the others
                if (!array_key_exists($cur->getConfigurationKey(), $data) || !empty($cur->getConfigurationValue())) {
                    $data[$cur->getConfigurationKey()] = $cur->getConfigurationValue();
                }
            }
        }

        return $data[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function setStubApiEndpoint($url = null): void
    {
        $this->setPluginConfigValue(self::CONFIG_KEY_SANDBOX_URL, (string) $url);
        $this->setPluginConfigValue(self::CONFIG_KEY_LIVE_URL, (string) $url);
        $this->setPluginConfigValue(self::CONFIG_KEY_THIRD_PARTY_SANDBOX_URL, (string) $url);
        $this->setPluginConfigValue(self::CONFIG_KEY_THIRD_PARTY_LIVE_URL, (string) $url);
    }

    /**
     * {@inheritDoc}
     */
    public function clearOauthTokensStorage(): void
    {
        $this->setPluginConfigValue(self::CONFIG_KEY_AUTH_TOKEN, '');
    }

    /**
     * {@inheritDoc}
     */
    public function getLastOrderId(): ?string
    {
        $result = $this->getLastOrderInfo();

        return is_array($result) && !empty($result['auto_increment']) ? $result['auto_increment'] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function isThirdPartySubscriptionEnabled(): bool
    {
        $this->clearConfigCache();
        return (bool) $this->getPluginConfigValue(self::CONFIG_KEY_PRODUCTS_SYNC_ENABLED);
    }

    /**
     * {@inheritDoc}
     */
    public function toggleThirdPartySubscription(): void
    {
        $this->clearConfigCache();
        $this->getSubscriptionManager()->toggleSubscription();
    }

    /**
     * {@inheritDoc}
     */
    public function doesProductExist($sku): bool
    {
        return null !== $this->getProductBySku($sku);
    }

    /**
     * {@inheritDoc}
     */
    public function getProductCategories($sku): array
    {
        $result = [];
        if ($product = $this->getProductBySku($sku)) {
            $result = $this->getCategoryManager()->getCategoryNames($product);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function removeProduct($sku): void
    {
        if ($product = $this->getProductBySku($sku)) {
            PayeverRegistry::set(PayeverRegistry::LAST_INWARD_PROCESSED_PRODUCT, $product);
            $this->getProductTransformer()->remove($product);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getProductInventoryValue($sku): ?int
    {
        $result = null;
        if ($product = $this->getProductBySku($sku)) {
            $result = $product->getAvailable() ? $product->getStock() : null;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getProductFieldValue($sku, $fieldName)
    {
        $fieldValue = null;
        $product = $this->getProductBySku($sku);
        if ($fieldName === 'visibility') {
            if ($visibilityCollection = $product->getVisibilities()) {
                /** @var ProductVisibilityEntity|null $visibilityEntity */
                if ($visibilityEntity = $visibilityCollection->first()) {
                    $fieldValue = $visibilityEntity->getVisibility();
                }
            }
        } elseif ($fieldName === 'gross') {
            $fieldValue = (string) $this->getPriceManager()->getGrossPrice($product);
        } elseif ($fieldName === 'net') {
            $fieldValue = (string) $this->getPriceManager()->getNetPrice($product);
        } elseif ($fieldName === 'linked') {
            $fieldValue = $this->getPriceManager()->getLinked($product);
        } elseif ($fieldName === 'vatRate') {
            $fieldValue = (string) $this->getPriceManager()->getVatRate($product);
        } elseif ($fieldName === 'currency') {
            $fieldValue = $this->getPriceManager()->getCurrencyIsoCode();
        } else {
            $fieldValue = $product->get($fieldName);
        }

        return $fieldValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getProductVariantFieldValue($sku, $fieldName)
    {
        return $this->getProductFieldValue($sku, $fieldName);
    }

    /**
     * @param string $sku
     * @param string $optionName
     * @param string $optionValueName
     * @return bool
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function getProductVariantOptionValueExists(string $sku, string $optionName, string $optionValueName): bool
    {
        $result = false;
        $product = $this->getProductBySku($sku);
        if ($product) {
            $propertyGroup = $this->getOptionManager()->getPropertyGroupByName($optionName);
            $propertyOption = $this->getOptionManager()->getPropertyGroupOptionByName($optionValueName);
            if ($propertyGroup && $propertyOption) {
                $result = in_array($propertyOption->getId(), $product->getOptionIds(), true);
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function productHasVariant($sku, $variantSku): bool
    {
        $result = false;
        $product = $this->getProductBySku($sku);
        if ($product && ($childrenCollection = $product->getChildren())) {
            foreach ($childrenCollection as $child) {
                if ($variantSku === $child->getProductNumber()) {
                    $result = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getProductUrl($sku): string
    {
        $product = $this->getStubProduct($sku);

        return sprintf('/detail/%s', bin2hex($product['id']));
    }

    /**
     * @return string|null
     */
    public function getStubProductSku()
    {
        $product = $this->getStubProduct();

        return $product['product_number'] ?? null;
    }

    /**
     * Clears cache
     */
    public function clearCache(): void
    {
        shell_exec(sprintf('cd %s && php ./psh.phar cache', $this->shopwareDir));
        $this->shopwareKernel->shutdown();
        $this->initShopwareKernel($this->getApplicationConfig());
        $this->router = $this->systemConfigService = $this->productRepository = $this->synchronizationQueueRepository =
            $this->paymentMethodRepository = $this->subscriptionManager = $this->priceManager = $this->categoryManager =
            $this->optionManager = $this->synchronizationQueueTaskHandler = $this->settingsService =
            $this->paymentOptionsService = $this->productTransformer = null;
    }

    /**
     * @param string $methodCode
     * @return array
     */
    public function findPaymentMethodsByCode(string $methodCode): array
    {
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM payment_method_translation pmt
                LEFT JOIN payment_method pm ON pm.id = pmt.payment_method_id
                WHERE custom_fields LIKE ?'
        ));
        $stmt->execute(["%$methodCode%"]);

        return $stmt->fetchAll();
    }

    /**
     * @return mixed|array|null
     */
    public function getLastOrderInfo()
    {
        $stmt = $this->pdo->prepare('
            SELECT smso.technical_name as order_state,
                smsot.technical_name as transaction_state,
                o.*,
                ot.*
            FROM `order` o
                LEFT JOIN order_transaction ot on ot.order_id = o.id
                LEFT JOIN state_machine_state smsot on smsot.id = ot.state_id
                LEFT JOIN state_machine_state smso on smso.id = o.state_id
            ORDER BY o.auto_increment DESC LIMIT 1;
        ');
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * @throws \UnexpectedValueException
     */
    protected function setupStubProduct(): void
    {
        $product = $this->getStubProduct();
        $productId = $product['id'];
        $productPrice = \json_decode($product['price'], true);
        foreach ($productPrice as &$price) {
            $price['net'] = 245.52;
            $price['gross'] = self::STUB_PRODUCT_GROSS_PRICE;
        }
        $stockStmt = $this->pdo->prepare(
            'UPDATE product SET stock = 1000, price = ?, available_stock = 1000,
                available = 1, active = 1, min_purchase = 1 WHERE id = ?'
        );
        $stockStmt->execute([\json_encode($productPrice), $productId]);
        $pricesStmt = $this->pdo->prepare('SELECT * FROM product_price WHERE product_id = ?');
        $pricesStmt->execute([$productId]);
        $priceUpdateStmt = $this->pdo->prepare('UPDATE product_price SET price = ? WHERE id = ?');
        while ($price = $pricesStmt->fetch()) {
            $priceData = \json_decode($price['price'], true);
            $priceDataKey = key($priceData);
            $priceData[$priceDataKey]['gross'] = self::STUB_PRODUCT_GROSS_PRICE;
            $priceData[$priceDataKey]['net'] = 254.24;
            $priceUpdateStmt->execute([\json_encode($priceData), $price['id']]);
        }
    }

    /**
     * @param string|null $sku
     * @return array
     *
     * @throws \UnexpectedValueException
     */
    protected function getStubProduct(string $sku = null): array
    {
        if ($sku) {
            $productStmt = $this->pdo->prepare('SELECT * FROM product WHERE product_number = ?');
            $productStmt->execute([$sku]);
        } else {
            $productStmt = $this->pdo->prepare('SELECT * FROM product ORDER BY auto_increment DESC LIMIT 1');
            $productStmt->execute();
        }
        $product = $productStmt->fetch();
        if (!$product) {
            throw new \UnexpectedValueException('Seems like your store has no products, we can not proceed');
        }

        return $product;
    }

    /**
     * @throws \Exception
     */
    public function setupPaymentMethods(): void
    {
        $this->getPaymentOptionsService()->synchronizePaymentOptions($this->getDefaultContext());
    }

    /**
     * Connects payment methods to sales channel
     */
    public function connectPaymentMethodsToSalesChannel(): void
    {
        $methodsStmt = $this->pdo->prepare('SELECT id FROM payment_method');
        $methodsStmt->execute();
        $methods = array_column($methodsStmt->fetchAll(), 'id');
        $channelsStmt = $this->pdo->prepare('SELECT id FROM sales_channel');
        $channelsStmt->execute();
        $updateSalesChannelStmt = $this->pdo->prepare(
            'UPDATE sales_channel SET payment_method_ids = ? WHERE id = ?'
        );
        $insertLinkStmt = $this->pdo->prepare(
            'INSERT IGNORE INTO sales_channel_payment_method (sales_channel_id, payment_method_id)
                VALUES (?, ?)'
        );
        while ($salesChannel = $channelsStmt->fetch()) {
            foreach ($methods as $method) {
                $insertLinkStmt->execute([$salesChannel['id'], $method]);
            }
            $hexMethods = array_map('bin2hex', $methods);
            $updateSalesChannelStmt->execute([\json_encode($hexMethods), $salesChannel['id']]);
        }
    }

    /**
     * Clears synchronization queue
     */
    public function clearSynchronizationQueue(): void
    {
        $queueItems = $this->getSynchronizationQueueRepository()->search(new Criteria(), $this->getDefaultContext())
            ->getEntities()
            ->getElements();
        $ids = [];
        /** @var \Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueEntity $queueItem */
        foreach ($queueItems as $queueItem) {
            $ids[] = ['id' => $queueItem->getId()];
        }
        if ($ids) {
            $this->getSynchronizationQueueRepository()->delete($ids, $this->getDefaultContext());
        }
    }

    /**
     * @return int
     */
    public function getSyncQueueCount(): int
    {
        return $this->getSynchronizationQueueRepository()->search(new Criteria(), $this->getDefaultContext())
            ->getTotal();
    }

    /**
     * @return int
     */
    public function getPaymentMethodsCount(): int
    {
        return $this->getPaymentMethodRepository()->search(new Criteria(), $this->getDefaultContext())
            ->getTotal();
    }

    /**
     * Runs synchronization queue task handler
     */
    public function runSynchronizationQueueTaskHandler(): void
    {
        $this->getSynchronizationQueueTaskHandler()
            ->setContext($this->getDefaultContext())
            ->run();
    }

    /**
     * Cleans media queued jobs populated during install
     */
    public function clearEnqueue(): void
    {
        $this->pdo->exec('DELETE FROM enqueue WHERE 1');
    }

    /**
     * @param int $limit
     * @param int $timeout
     */
    public function runMessageConsumer($limit = 100, $timeout = 60): void
    {
        shell_exec(
            sprintf(
                'cd %s && php ./bin/console messenger:consume -vv --limit=%d --time-limit=%d',
                $this->shopwareDir,
                $limit,
                $timeout
            )
        );
    }

    /**
     * Sets up currencies and rates
     */
    protected function setupCurrenciesAndRates(): void
    {
        $currencies = [
            'NOK' => 10,
            'DKK' => 8,
            'SEK' => 11,
        ];
        $channelsStmt = $this->pdo->prepare('SELECT id FROM sales_channel');
        $channelsStmt->execute();
        $salesChannels = array_column($channelsStmt->fetchAll(), 'id');
        $insertChannelLinkStmt = $this->pdo->prepare(
            'INSERT IGNORE INTO sales_channel_currency (sales_channel_id, currency_id) VALUES (?, ?)'
        );
        $getCurrencyStmt = $this->pdo->prepare('SELECT id FROM currency WHERE iso_code = ?');
        $insertCurrencyStmt = $this->pdo->prepare('
            INSERT INTO currency (id, iso_code, factor, symbol, decimal_precision, created_at)
                VALUES (RANDOM_BYTES(16), ?, ?, ?, 2, NOW());
        ');
        $insertCurrencyLangStmt = $this->pdo->prepare('
            INSERT INTO currency_translation (currency_id, language_id, short_name, name, custom_fields, created_at)
                VALUES (?, (SELECT id FROM language WHERE language.name = "English"), ?, ?, "[]", NOW())
        ');
        $updateRateStmt = $this->pdo->prepare('UPDATE currency SET factor = ? WHERE id = ?');
        foreach ($currencies as $code => $rate) {
            $getCurrencyStmt->execute([$code]);
            $currencyId = $getCurrencyStmt->fetchColumn();
            if (!$currencyId) {
                $insertCurrencyStmt->execute([$code, $rate, $code]);
                $getCurrencyStmt->execute([$code]);
                $newCurrencyId = $getCurrencyStmt->fetchColumn();
                $insertCurrencyLangStmt->execute([$newCurrencyId, $code, $code]);
                foreach ($salesChannels as $salesChannelId) {
                    $insertChannelLinkStmt->execute([$salesChannelId, $newCurrencyId]);
                }
            } else {
                $updateRateStmt->execute([$rate, $currencyId]);
            }
        }
    }

    /**
     * @return void
     */
    private function configureEmailTemplates()
    {
        if ($this->shopwareVersion && !version_compare($this->shopwareVersion, '6.2', '<')) {
            return;
        }
        $this->pdo->exec('TRUNCATE mail_template_sales_channel');
        $this->pdo->exec(
"INSERT
INTO mail_template_sales_channel
SELECT mt.id, mt.id, mt.mail_template_type_id, ct.sales_channel_id, CURRENT_TIMESTAMP, null FROM mail_template mt
inner join sales_channel_translation ct WHERE `name`='Storefront'"
        );
        $this->clearCache();
    }

    /**
     * @param string $sku
     * @return ProductEntity|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function getProductBySku(string $sku): ?ProductEntity
    {
        return $this->getProductRepository()
            ->search(
                $this->getProductTransformer()
                    ->getBaseProductCriteria()
                    ->addFilter(new EqualsFilter('productNumber', trim($sku))),
                $this->getDefaultContext()
            )
            ->getEntities()
            ->first();
    }

    /**
     * @return array
     */
    private function getApplicationConfig(): array
    {
        $configPath = sprintf('%s/.psh.yaml.override', $this->shopwareDir);
        if (!file_exists($configPath) || !is_readable($configPath)) {
            throw new \RuntimeException(sprintf('Shopware config %s is not accessible', $configPath));
        }
        $config = Yaml::parseFile($configPath);
        if (!isset($config['const']['DB_HOST'])) {
            throw new \UnexpectedValueException(
                sprintf('Shopware config at path %s does not contain DB connection details', $configPath)
            );
        }

        return $config;
    }

    /**
     * @return Context
     */
    private function getDefaultContext(): Context
    {
        return new DisabledCacheContext(new SystemSource());
    }

    /**
     * @return Connection
     */
    private function getConnection()
    {
        if (null === $this->connection) {
            $this->connection = Kernel::getConnection();
        }

        return $this->connection;
    }

    /**
     * @return RouterInterface|null
     */
    private function getRouter(): ?RouterInterface
    {
        if (null === $this->router) {
            $this->router = $this->shopwareKernel->getContainer()
                ->get('router', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        }

        return $this->router;
    }

    /**
     * @return SystemConfigService
     */
    private function getSystemConfigService(): SystemConfigService
    {
        if (null === $this->systemConfigService) {
            $this->systemConfigService = $this->shopwareKernel->getContainer()->get(SystemConfigService::class);
        }

        return $this->systemConfigService;
    }

    /**
     * @return EntityRepositoryInterface
     */
    private function getSystemConfigRepository()
    {
        if (null === $this->systemConfigRepository) {
            $this->systemConfigRepository = $this->shopwareKernel->getContainer()->get('system_config.repository');
        }

        return $this->systemConfigRepository;
    }

    /**
     * @return EntityRepositoryInterface
     */
    private function getProductRepository(): EntityRepositoryInterface
    {
        if (null === $this->productRepository) {
            $this->productRepository = $this->shopwareKernel->getContainer()->get('product.repository');
        }

        return $this->productRepository;
    }

    /**
     * @return EntityRepositoryInterface
     */
    private function getPaymentMethodRepository(): EntityRepositoryInterface
    {
        if (null === $this->paymentMethodRepository) {
            $this->paymentMethodRepository = $this->shopwareKernel
                ->getContainer()
                ->get('payment_method.repository');
        }

        return $this->paymentMethodRepository;
    }

    /**
     * @return EntityRepositoryInterface
     */
    private function getSynchronizationQueueRepository(): EntityRepositoryInterface
    {
        if (null === $this->synchronizationQueueRepository) {
            $this->synchronizationQueueRepository = $this->shopwareKernel
                ->getContainer()
                ->get('payever_synchronization_queue.repository');
        }

        return $this->synchronizationQueueRepository;
    }

    /**
     * @return SubscriptionManager
     */
    public function getSubscriptionManager(): SubscriptionManager
    {
        if (null === $this->subscriptionManager) {
            $this->subscriptionManager = $this->shopwareKernel->getContainer()->get(SubscriptionManager::class);
        }

        return $this->subscriptionManager;
    }

    /**
     * @return PriceManager
     */
    private function getPriceManager(): PriceManager
    {
        if (null === $this->priceManager) {
            $this->priceManager = $this->shopwareKernel->getContainer()->get(PriceManager::class);
        }

        return $this->priceManager;
    }

    /**
     * @return CategoryManager
     */
    private function getCategoryManager(): CategoryManager
    {
        if (null === $this->categoryManager) {
            $this->categoryManager = $this->shopwareKernel->getContainer()->get(CategoryManager::class);
        }

        return $this->categoryManager;
    }

    /**
     * @return OptionManager
     */
    private function getOptionManager(): OptionManager
    {
        if (null === $this->optionManager) {
            $this->optionManager = $this->shopwareKernel->getContainer()->get(OptionManager::class);
        }

        return $this->optionManager;
    }

    /**
     * @return SynchronizationQueueTaskHandler
     */
    private function getSynchronizationQueueTaskHandler(): SynchronizationQueueTaskHandler
    {
        if (null === $this->synchronizationQueueTaskHandler) {
            $this->synchronizationQueueTaskHandler = $this->shopwareKernel
                ->getContainer()
                ->get(SynchronizationQueueTaskHandler::class);
        }

        return $this->synchronizationQueueTaskHandler;
    }

    /**
     * @return SettingsService|null
     */
    private function getSettingsService(): ?SettingsService
    {
        if (null === $this->settingsService && $this->shopwareKernel->getContainer()->has(SettingsService::class)) {
            $this->settingsService = $this->shopwareKernel->getContainer()->get(SettingsService::class);
        }

        return $this->settingsService;
    }

    /**
     * @return PaymentOptionsService
     */
    private function getPaymentOptionsService(): PaymentOptionsService
    {
        if(null === $this->paymentOptionsService) {
            $this->paymentOptionsService = $this->shopwareKernel
                ->getContainer()
                ->get(PaymentOptionsService::class);
        }

        return $this->paymentOptionsService;
    }

    /**
     * @return ProductTransformer
     */
    private function getProductTransformer(): ProductTransformer
    {
        if (null === $this->productTransformer) {
            $this->productTransformer = $this->shopwareKernel->getContainer()->get(ProductTransformer::class);
        }

        return $this->productTransformer;
    }

    /**
     * Setup totals for the last order.
     *
     * @param string $field
     * @param string $value
     *
     * @return void
     * @throws \Exception
     */
    public function addTotals($field, $value): void
    {
        if (!in_array($field, ['captured_total', 'cancelled_total', 'refunded_total'])) {
            throw new \Exception('Invalid field');
        }

        $info = $this->getLastOrderInfo();
        if (!$info) {
            return;
        }

        $orderId = $info['order_id'];

        $stmt = $this->pdo->prepare('SELECT id FROM payever_order_totals WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $totalId = $stmt->fetchColumn();
        if (!$totalId) {
            // Insert new record
            $sql = "INSERT INTO payever_order_totals (id, order_id, created_at) VALUES (?, ?, NOW());";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->getRandomBytes(),
                $orderId,
            ]);

            $stmt = $this->pdo->prepare('SELECT id FROM payever_order_totals WHERE order_id = ?');
            $stmt->execute([$orderId]);
            $totalId = $stmt->fetchColumn();

            if (!$totalId) {
                throw new \Exception('Unable to add totals.');
            }
        }

        $sql = "UPDATE payever_order_totals SET {$field} = ?, updated_at = NOW() WHERE id = ?;";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $value,
            $totalId
        ]);
    }


    private function getRandomBytes()
    {
        if (class_exists('Uuid')) {
            return Uuid::randomBytes();
        }

        return random_bytes(16);
    }
}
