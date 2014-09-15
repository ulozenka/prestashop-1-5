<?php

include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');
$val = Tools::getValue('selpobocka');
$retval['refresh'] = 1;  // OPC=1 else 0
if (strlen($val) && strlen(Context::getContext()->cookie->ulozenka)) {
    $retval['refresh'] = 0;
}
$instance = Module::getInstanceByName('ulozenka');
Context::getContext()->cookie->ulozenka = $val;
$ceny = json_decode(Configuration::get('ULOZENKA_POBOCKY'), true);
if (isset($ceny[$val]) && strlen($ceny[$val])) {
    $retval['cena'] = Tools::displayPrice(Tools::convertPrice($instance->addTax($ceny[$val])));
}

if ($retval['cena'] == '')
    $retval['cena'] = Tools::displayPrice(Tools::convertPrice($instance->addTax($instance->getDefaultPrice())));

$retval['cena'].=' (' . $instance->l('s DPH') . ')';


if ($instance::isFreeShipping(Context::getContext()->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS)))
    $retval['cena'] = $instance->l('Zdarma');



$retval['platba'] = $instance->ajax_getPaymentMethods();
if ($val && strlen($val)) {

    $retval['allow'] = 1;
} else {
    $retval['allow'] = 0;
}



$retval['opc'] = (int) Configuration::get('PS_ORDER_PROCESS_TYPE');

$retval['version'] = $instance::getVersion();
die(json_encode($retval));
?>
