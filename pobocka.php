<?php

include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');

$code = Tools::getValue('code');
$instance = Module::getInstanceByName('ulozenka');
$pobocka = $instance->getPobockaByZkratka($code);
if ($pobocka && is_array($pobocka)) {

    $output = $instance->displayPobocka($pobocka, false);
    echo $output;
}
?>
