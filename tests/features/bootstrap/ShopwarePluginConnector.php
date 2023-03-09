<?php

namespace Payever\Tests;

use Assert\Assertion;
use Payever\Stub\BehatExtension\ServiceContainer\PluginConnectorInterface;
use Symfony\Component\Yaml\Yaml;
use Shopware\Core\Framework\Uuid\Uuid;

class ShopwarePluginConnector implements PluginConnectorInterface
{
    public const PLUGIN_CODE = 'PevrPayeverIntegration';
    public const STUB_PRODUCT_GROSS_PRICE = 300;
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

    /** @var string */
    private $euroCurrencyId;

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

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if (file_exists($shopwareDir . '/vendor/shopware/core/Framework/Uuid/Uuid.php')) {
            require_once $shopwareDir . '/vendor/shopware/core/Framework/Uuid/Uuid.php';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function prepareCmsConfig(): void
    {
        $this->setupStubProduct();
        $this->setupCurrenciesAndRates();
        $this->configureEmailTemplates();
        $this->clearEnqueue();
        $this->clearSynchronizationQueue();
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
        $stmt = $this->pdo->prepare('DELETE FROM system_config WHERE configuration_key = ?');
        $stmt->execute([$key]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO system_config VALUES (?, ?, ?, NULL, NOW(), NOW())'
        );
        $stmt->execute([
            $this->getRandomBytes(),
            $key,
            \json_encode(['_value' => $value]),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getPluginConfigValue($key)
    {
        $stmt = $this->pdo->prepare('SELECT configuration_value FROM system_config WHERE configuration_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        if (!$value) {
            return null;
        }
        $value = \json_decode($value, true);

        return $value['_value'] ?? null;
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
        return (bool) $this->getPluginConfigValue(self::CONFIG_KEY_PRODUCTS_SYNC_ENABLED);
    }

    /**
     * {@inheritDoc}
     */
    public function toggleThirdPartySubscription(): void
    {
        $isEnabled = $this->isThirdPartySubscriptionEnabled();
        $this->setPluginConfigValue(self::CONFIG_KEY_PRODUCTS_SYNC_ENABLED, !$isEnabled);
        $externalId = 'externalIdHash';
        if ($isEnabled) {
            $externalId = null;
        }
        $this->setPluginConfigValue(self::CONFIG_KEY_PRODUCTS_SYNC_EXTERNAL_ID, $externalId);
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
        $query = <<<SQL
SELECT ct.name FROM category_translation ct
INNER JOIN category c ON c.id = ct.category_id AND c.version_id = ct.category_version_id
INNER JOIN product_category pc ON pc.category_id = c.id AND pc.category_version_id = c.version_id
INNER JOIN product p ON p.id = pc.product_id AND pc.product_version_id = p.version_id
LEFT JOIN language l ON ct.language_id = l.id
WHERE p.product_number = ?
AND l.name = 'English'
SQL;
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$sku]);
        $rows = $stmt->fetchAll();
        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $result[] = $row['name'];
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function removeProduct($sku): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM product WHERE product_number = ?');
        $stmt->execute([$sku]);
    }

    /**
     * {@inheritDoc}
     */
    public function getProductInventoryValue($sku): ?int
    {
        $result = null;
        if ($product = $this->getProductBySku($sku)) {
            $result = $product['available'] ? $product['available_stock'] : null;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getProductFieldValue($sku, $fieldName)
    {
        $product = $this->getProductBySku($sku);

        return $product[$fieldName] ?? null;
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
        $optionIds = $product && !empty($product['option_ids']) ? \json_decode($product['option_ids']) : [];
        if ($product && $optionIds) {
            $query = <<<SQL
SELECT pgt.property_group_id FROM property_group_translation pgt
LEFT JOIN language l ON l.id = pgt.language_id
AND pgt.name = ?
AND l.name = 'English'
SQL;
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$optionName]);
            $propertyGroupExists = (bool) $stmt->fetchColumn();
            if ($propertyGroupExists) {
                $query = <<<SQL
SELECT pgot.property_group_option_id FROM property_group_option_translation pgot
LEFT JOIN language l ON l.id = pgot.language_id
AND pgot.name = ?
AND l.name = 'English'
SQL;
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$optionValueName]);
                $propertyOptionId = $stmt->fetchColumn();
                $result = $propertyOptionId && in_array(bin2hex($propertyOptionId), $optionIds, true);
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
        if ($product) {
            $query = <<<SQL
SELECT product_number FROM product WHERE parent_id = ? AND product_number = ?
SQL;
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$product['id'], $variantSku]);
            $result = (bool) $stmt->fetchColumn();
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
            SELECT smso.technical_name AS order_state,
                smsot.technical_name AS transaction_state,
                o.*,
                ot.*
            FROM `order` o
                LEFT JOIN order_transaction ot ON ot.order_id = o.id
                LEFT JOIN state_machine_state smsot ON smsot.id = ot.state_id
                LEFT JOIN state_machine_state smso ON smso.id = o.state_id
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
        //$this->pdo->exec('DELETE FROM payever_synchronization_queue WHERE 1');
    }

    /**
     * @return int
     */
    public function getSyncQueueCount(): int
    {
        $methodsStmt = $this->pdo->prepare('SELECT COUNT(*) AS cnt FROM payever_synchronization_queue');
        $methodsStmt->execute();

        return (int) $methodsStmt->fetchColumn();
    }

    /**
     * @return int
     */
    public function getPaymentMethodsCount(): int
    {
        $methodsStmt = $this->pdo->prepare('SELECT COUNT(*) AS cnt FROM payment_method');
        $methodsStmt->execute();

        return (int) $methodsStmt->fetchColumn();
    }

    /**
     * Runs synchronization queue task handler
     */
    public function runSynchronizationQueueTaskHandler(): void
    {
        $query = <<<SQL
UPDATE scheduled_task
SET status = ?, last_execution_time = ?, next_execution_time = ?
WHERE name = ?
SQL;
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            'scheduled',
            '2021-05-01 00:00:00.001',
            '2021-05-01 00:00:00.001',
            'payever.synchronization_queue_task'
        ]);
        shell_exec(sprintf(
            'cd %s && ./bin/console scheduled-task:run --time-limit=5',
            $this->shopwareDir
        ));
        $this->runMessageConsumer(50, 30);
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

        // SHOW COLUMNS FROM currency LIKE 'decimal_precision'
        $checkColumnStmt = $this->pdo->prepare("SHOW COLUMNS FROM currency LIKE 'decimal_precision'");
        $checkColumnStmt->execute();
        $column = $checkColumnStmt->fetchColumn();
        $noDecimalPrecision = $column === false;

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

        // decimal_precision is missing. See vendor/shopware/core/Migration/V6_3/Migration1610965670RemoveDeprecatedColumns.php
        // @todo decimal_precision is missing
        if ($noDecimalPrecision) {
            $insertCurrencyStmt = $this->pdo->prepare('
                INSERT INTO currency (id, iso_code, factor, symbol, item_rounding, total_rounding, created_at)
                    VALUES (:id, :code, :rate, :code, :item_rounding, :total_rounding, NOW());
            ');
        } else {
            $insertCurrencyStmt = $this->pdo->prepare('
                INSERT INTO currency (id, iso_code, factor, symbol, decimal_precision, created_at)
                    VALUES (:id, :code, :rate, :code, 2, NOW());
            ');
        }

        $insertCurrencyLangStmt = $this->pdo->prepare('
            INSERT INTO currency_translation (currency_id, language_id, short_name, name, custom_fields, created_at)
                VALUES (?, (SELECT id FROM language WHERE language.name = "English"), ?, ?, "[]", NOW())
        ');
        $updateRateStmt = $this->pdo->prepare('UPDATE currency SET factor = ? WHERE id = ?');
        foreach ($currencies as $code => $rate) {
            $getCurrencyStmt->execute([$code]);
            $currencyId = $getCurrencyStmt->fetchColumn();
            if (!$currencyId) {
                $insertCurrencyStmt->execute([
                    ':id' => $this->getRandomBytes(),
                    ':code' => $code,
                    ':rate' => $rate,
                    ':item_rounding' => '{"decimals":2,"interval":0.01,"roundForNet":1}',
                    ':total_rounding' => '{"decimals":2,"interval":0.01,"roundForNet":1}'
                ]);

                $getCurrencyStmt->execute([$code]);
                $newCurrencyId = $getCurrencyStmt->fetchColumn();

                if (!$newCurrencyId) {
                    throw new \Exception('Failed to install currency.');
                }

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
     * @return array|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function getProductBySku(string $sku)
    {
        $result = null;
        $query = <<<SQL
SELECT p.*, pt.name, pt.description, pv.visibility, t.tax_rate AS vatRate FROM product p
LEFT JOIN product_translation pt ON p.id = pt.product_id AND p.version_id = pt.product_version_id
LEFT JOIN language l ON pt.language_id = l.id
LEFT JOIN product_visibility pv on p.id = pv.product_id and p.version_id = pv.product_version_id
LEFT JOIN tax t on p.tax_id = t.id
WHERE p.product_number = ?
AND l.name = 'English'
SQL;
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$sku]);
        $product = $stmt->fetch();
        if (is_array($product)) {
            $result = $product;
            if (!empty($result['price'])) {
                $prices = \json_decode($result['price'], true);
                if (is_array($prices)) {
                    $price = [
                        'net' => 0,
                        'gross' => 0,
                        'linked' => false,
                    ];
                    foreach ($prices as $priceData) {
                        if ($this->getEuroCurrencyId() === $priceData['currencyId']) {
                            $price = $priceData;
                            break;
                        }
                    }
                    $result['net'] = (float) $price['net'];
                    $result['gross'] = (float) $price['gross'];
                    $result['linked'] = (bool) $price['linked'];
                    $result['currency'] = 'EUR';
                }
            }
            if (!$result['visibility']) {
                $result['visibility'] = '30'; //default value
            }
        }

        return $result;
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
     * @return string
     */
    private function getEuroCurrencyId(): ?string
    {
        if (null === $this->euroCurrencyId) {
            $stmt = $this->pdo->prepare('SELECT id FROM currency where iso_code = ?');
            $stmt->execute(['EUR']);
            $this->euroCurrencyId = bin2hex($stmt->fetchColumn());
        }

        return $this->euroCurrencyId;
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
