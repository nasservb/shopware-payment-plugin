<?php

use Payever\PayeverPayments\Controller\AllowedActionsController;

defined('SHOPWARE_SOURCE_PATH') || define('SHOPWARE_SOURCE_PATH', '/var/www/app');
defined('PLUGIN_SOURCE_PATH') || define('PLUGIN_SOURCE_PATH', '/var/www/app/custom/plugins/PevrPayeverIntegration');

require_once SHOPWARE_SOURCE_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once PLUGIN_SOURCE_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

spl_autoload_register(function ($class) {
    $file = sprintf(
        '%s/src%s.php',
        PLUGIN_SOURCE_PATH,
        str_replace(
            '\\', DIRECTORY_SEPARATOR, str_replace('Payever\PayeverPayments', '', $class)
        )
    );
    if (file_exists($file)) {
        require_once $file;
    }
});
