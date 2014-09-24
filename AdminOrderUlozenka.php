<?php

/*
 * 2007-2013 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2013 PrestaShop SA
 *  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

class AdminOrderUlozenka extends AdminController {

    public $toolbar_title;

    public function __construct() {
        $this->bootstrap = true;
        $this->table = 'order';
        $this->className = 'Order';
        $this->lang = false;
        //$this->addRowAction('view');
        $this->explicitSelect = true;
        $this->allow_export = false;
        //$this->lite_display=true;
        $this->deleted = false;
        $this->context = Context::getContext();
        $this->bulk_actions = array(
            'exportU' => array('text' => $this->l('export Ulozenka'), 'icon' => 'icon-refresh')
        );

        $this->_select = '
        a.id_currency,
        a.id_order AS id_pdf,
        cr.iso_code AS currency,
        CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) AS `customer`,
        osl.`name` AS `osname`,
        os.`color`,
        u.`dobirka`, u.`exported`, u.`id_ulozenka`,  u.`pobocka_name`, u.`date_exp`,
        IF((SELECT COUNT(so.id_order) FROM `' . _DB_PREFIX_ . 'orders` so WHERE so.id_customer = a.id_customer) > 1, 0, 1) as new';

        $this->_join = '
        LEFT JOIN `' . _DB_PREFIX_ . 'currency` cr ON a.`id_currency` = cr.`id_currency`
        LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.`id_customer` = a.`id_customer`)
        LEFT JOIN `' . _DB_PREFIX_ . 'ulozenka` u ON (u.`id_order` = a.`id_order`)
        LEFT JOIN `' . _DB_PREFIX_ . 'order_state` os ON (os.`id_order_state` = a.`current_state`)
        LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = ' . (int) $this->context->language->id . ')';

        $this->_where .='AND u.`id_order` > 0';

        $this->_orderBy = 'id_order';
        $this->_orderWay = 'DESC';

        $statuses_array = array();
        $statuses = OrderState::getOrderStates((int) $this->context->language->id);

        foreach ($statuses as $status)
            $statuses_array[$status['id_order_state']] = $status['name'];


        $this->fields_list = array(
            'id_order' => array(
                'title' => $this->l('ID'),
                'align' => 'center',
                'width' => 25
            ),
            'reference' => array(
                'title' => $this->l('Označení'),
                'align' => 'center',
                'width' => 65
            ),
            'id_ulozenka' => array(
                'title' => $this->l('id uloženka'),
                'align' => 'center',
                'width' => 25
            ),
            'customer' => array(
                'title' => $this->l('Zákazník'),
                'havingFilter' => true,
            ),
            'total_paid_tax_incl' => array(
                'title' => $this->l('Celkem'),
                'width' => 70,
                'align' => 'center',
                'prefix' => '<b>',
                'suffix' => '</b>',
                'type' => 'float'
            ),
            'currency' => array(
                'title' => $this->l('Měna'),
                'width' => 50,
                'prefix' => '<b>',
                'suffix' => '</b>',
                'align' => 'center',
                'filter_key' => 'currency'
            ),
            'payment' => array(
                'title' => $this->l('Platba: '),
                'width' => 100
            ),
            'osname' => array(
                'title' => $this->l('Stav'),
                'color' => 'color',
                'width' => 100,
                'type' => 'select',
                'list' => $statuses_array,
                'filter_key' => 'os!id_order_state',
                'filter_type' => 'int',
                'order_key' => 'osname'
            ),
            'pobocka_name' => array(
                'title' => $this->l('Pobočka'),
                'width' => 150,
                'align' => 'right',
                'filter_key' => 'pobocka_name'
            ),
            'date_add' => array(
                'title' => $this->l('Datum objednávky'),
                'width' => 130,
                'align' => 'right',
                'type' => 'datetime',
                'filter_key' => 'a!date_add'
            ),
            'date_exp' => array(
                'title' => $this->l('Datum exportu'),
                'width' => 130,
                'align' => 'right',
                'type' => 'datetime',
                'filter_key' => 'date_exp'
            ),
            'exported' => array(
                'title' => $this->l('Exportováno'),
                'width' => 70,
                'align' => 'center',
                'type' => 'bool',
                'active' => 'exported',
                'filter_key' => 'exported'
            ),
            'dobirka' => array(
                'title' => $this->l('Dobírka'),
                'width' => 70,
                'align' => 'center',
                'type' => 'bool',
                'active' => 'dobirka',
                'filter_key' => 'dobirka'
            ),
        );

        $this->shopLinkType = 'shop';
        $this->shopShareDatas = Shop::SHARE_ORDER;

        if (Tools::isSubmit('id_order')) {
            // Save context (in order to apply cart rule)
            $order = new Order((int) Tools::getValue('id_order'));
            if (!Validate::isLoadedObject($order))
                throw new PrestaShopException('Cannot load Order object');
            $this->context->cart = new Cart($order->id_cart);
            $this->context->customer = new Customer($order->id_customer);
        }

        parent::__construct();
    }

    public function processDobirka() {
        $sql = 'SELECT dobirka FROM ' . _DB_PREFIX_ . 'ulozenka WHERE id_order=' . (int) Tools::getValue('id_order');
        $dobirka = Db::getInstance()->getValue($sql);
        if (!($dobirka === false)) {
            // $dobirka=!(int)$dobirka;
            if ((int) $dobirka == 1)
                $sql = 'UPDATE   ' . _DB_PREFIX_ . 'ulozenka SET dobirka=0  WHERE id_order=' . (int) Tools::getValue('id_order');
            else
                $sql = 'UPDATE   ' . _DB_PREFIX_ . 'ulozenka SET dobirka=1  WHERE id_order=' . (int) Tools::getValue('id_order');
            Db::getInstance()->execute($sql);
            $this->renderList();
            return true;
        }
    }

    public function processExported() {
        $sql = 'SELECT exported FROM ' . _DB_PREFIX_ . 'ulozenka WHERE id_order=' . (int) Tools::getValue('id_order');
        $exported = Db::getInstance()->getValue($sql);
        if (!($exported === false)) {
            // $dobirka=!(int)$dobirka;
            if ((int) $exported == 1)
                $sql = 'UPDATE   ' . _DB_PREFIX_ . 'ulozenka SET exported=0  WHERE id_order=' . (int) Tools::getValue('id_order');
            else
                $sql = 'UPDATE   ' . _DB_PREFIX_ . 'ulozenka SET exported=1  WHERE id_order=' . (int) Tools::getValue('id_order');
            Db::getInstance()->execute($sql);
            $this->renderList();
            return true;
        }
    }

    protected function processBulkExportU() {
        if (is_array($this->boxes) && !empty($this->boxes)) {
            $exportu = Module::getInstanceByName('ulozenka');

            $errors = $exportu->exportOrders($this->boxes);
            if ($errors === false)
                $this->confirmations[] = Tools::displayError('Data byla úspěšně exportována.');
            else
                $this->errors = array_merge($this->errors, $errors);
        } else
            $this->errors[] = Tools::displayError('You must select at least one element to export.');
    }

    public function renderForm() {
        
    }

    public function initToolbar() {
        if ($this->display == 'view') {
            $order = new Order((int) Tools::getValue('id_order'));
        }
        $res = parent::initToolbar();
        if (Context::getContext()->shop->getContext() != Shop::CONTEXT_SHOP && isset($this->toolbar_btn['new']) && Shop::isFeatureActive())
            unset($this->toolbar_btn['new']);
        return $res;
    }

    public function setMedia() {
        parent::setMedia();
        $this->addJqueryUI('ui.datepicker');
        if ($this->tabAccess['edit'] == 1 && $this->display == 'view') {
            $this->addJS(_PS_JS_DIR_ . 'admin_order.js');
            $this->addJS(_PS_JS_DIR_ . 'tools.js');
            $this->addJqueryPlugin('autocomplete');
        }
    }

    public function postProcess() {

        if (isset($_GET['dobirkaorder']) && Tools::getValue('id_order') > 0) {
            return $this->processDobirka();
        }
        if (isset($_GET['exportedorder']) && Tools::getValue('id_order') > 0) {
            return $this->processExported();
        }
        if (Tools::isSubmit('updateorder') && Tools::getValue('id_order')) {
            $link = Context::getContext()->link->getAdminLink('AdminOrders');
            $link.='&id_order=' . Tools::getValue('id_order') . '&vieworder';
            Tools::redirectAdmin($link);
        }
        // If id_order is sent, we instanciate a new Order object
        if (Tools::isSubmit('id_order') && Tools::getValue('id_order') > 0) {
            $order = new Order(Tools::getValue('id_order'));
            if (!Validate::isLoadedObject($order))
                throw new PrestaShopException('Can\'t load Order object');
            ShopUrl::cacheMainDomainForShop((int) $order->id_shop);
        }



        parent::postProcess();
    }

    public function ajaxProcessSearchCustomers() {
        if ($customers = Customer::searchByName(pSQL(Tools::getValue('customer_search'))))
            $to_return = array('customers' => $customers,
                'found' => true);
        else
            $to_return = array('found' => false);
        $this->content = Tools::jsonEncode($to_return);
    }

    /**
     * Function used to render the list to display for this controller
     */
    public function renderList() {
        if (!($this->fields_list && is_array($this->fields_list)))
            return false;
        $this->getList($this->context->language->id);

        $helper = new HelperList();

        // Empty list is ok
        if (!is_array($this->_list)) {
            $this->displayWarning($this->l('Bad SQL query', 'Helper') . '<br />' . htmlspecialchars($this->_list_error));
            return false;
        }

        $this->setHelperDisplay($helper);
        $helper->tpl_vars = $this->tpl_list_vars;
        $helper->tpl_delete_link_vars = $this->tpl_delete_link_vars;
        $helper->show_toolbar = false;
        // For compatibility reasons, we have to check standard actions in class attributes
        foreach ($this->actions_available as $action) {
            if (!in_array($action, $this->actions) && isset($this->$action) && $this->$action)
                $this->actions[] = $action;
        }
        if (Tools::version_compare(_PS_VERSION_, '1.5.2', '>'))
            $helper->is_cms = $this->is_cms;

        $helper->force_show_bulk_actions = true;
        $list = $helper->generateList($this->_list, $this->fields_list);

        return $list;
    }

}
