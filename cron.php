<?php
require_once dirname(__FILE__) . '/../../config/config.inc.php';
// require_once dirname(__FILE__) . '/../../init.php';

require_once dirname(__FILE__) . '/ps_victronproducts.php';

$module = new Ps_VictronProducts();

if (Validate::isLoadedObject($module)) {
    // Security Check
    $token = Tools::getValue('token');
    $secureKey = Configuration::get('VICTRON_SECURE_KEY');

    if (empty($secureKey) || $token !== $secureKey) {
        header('HTTP/1.1 403 Forbidden');
        die('Error: Invalid or missing security token.');
    }

    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '512M');

    echo "Starting synchronisation...\n";
    if ($module->runSync()) {
        echo "Synchronisation completed successfully.";
    }
    else {
        echo "Synchronisation failed.";
    }
}
else {
    echo "Error: Module could not be loaded.";
}