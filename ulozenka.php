<?php

if (!defined('_PS_VERSION_'))
    exit;

class Ulozenka extends CarrierModule {

    protected $uppername;
    protected $default_price = false;
    protected $tax_rate = false;

    public function __construct() {
        $this->name = 'ulozenka';
        $this->uppername = strtoupper($this->name);
        $this->tab = 'shipping_logistics';
        $this->version = '1.4';
        $this->author = 'prestahost.cz';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6.9');
        //   $this->dependencies = array('blockcart');

        parent::__construct();

        $this->displayName = $this->l('Ulozenka');
        $this->description = $this->l('Module for ulozenka.cz');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('ULOZENKA_ACCESS_CODE'))
            $this->warning = $this->l('No ulozenka access code provided');
    }

    public function hookDisplayHeader() {
        $this->context->controller->addJS(($this->_path) . 'js/ulozenka.js');
    }

    public function getOrderShippingCost($params, $shipping_cost) {
        return $this->getShippingCost($params, $shipping_cost);
    }

    public function getOrderShippingCostExternal($params) {
        return $this->getShippingCost($params);
    }

    protected function getShippingCost($params) {

        $total = $params->getOrderTotal(true, Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING);
        if (self::isFreeShipping($total))
            return 0;


        if ($params->id_carrier && $params->id_carrier != Configuration::get($this->uppername . '_CARRIER_ID')) {
            return Tools::convertPrice($this->getDefaultPrice(false));
        }
        if (isset(Context::getContext()->cookie->{$this->name})) {
            $code = Context::getContext()->cookie->{$this->name};
            $pobocky = $this->getUlozenkaItems();
            foreach ($pobocky as $pobocka) {
                if ($pobocka['shortcut'] == $code) {
                    $ceny = json_decode(Configuration::get('ULOZENKA_POBOCKY'), true);
                    $cena = $this->pobockaCena($ceny, $pobocka['shortcut']);
                    if ($cena == '')
                        $cena = $this->getDefaultPrice();
                    $cena = Tools::convertPrice($cena);
                    return $cena;
                }
            }
        }




        return Tools::convertPrice($this->getDefaultPrice());
    }

    public static function isFreeShipping($total) {

        if ((int) Configuration::get('ULOZENKA_SHIPPING_FREE') > 0 &&
                $total > Tools::convertPrice(Configuration::get('ULOZENKA_SHIPPING_FREE')))
            return true;

        return false;
    }

    public function getDefaultPrice($add_tax = false) {
        if ($this->default_price === false) {
            $default = (float) Configuration::get($this->uppername . '_DEFAULT_PRICE');
            if ($default) {
                $this->default_price = $default;
            } else {

                $ceny = json_decode(Configuration::get('ULOZENKA_POBOCKY'), true);
                foreach ($ceny as $cena) {
                    if ((int) $cena > 0)
                        $this->default_price = (int) $cena;
                }
            }
        }
        if ($this->default_price === false) {
            $this->default_price = 0;
        }
        if ($add_tax)
            return $this->addTax($this->default_price);

        return $this->default_price;
    }

    public function install() {

        if (parent::install() == false)
            return false;

        require_once(_PS_MODULE_DIR_ . $this->name . '/PrestahostModuleInstall.php');
        $install = new PrestahostModuleInstall($this);

        if (!$install->addState('OS_ULOZENKA_DORUCENO', array('en' => 'Ulozenka delivered', 'cs' => 'Ulozenka doručeno'), '#FFD5D5', 0, 1))
            return false;


        $carrierConfig = array(
            0 => array('name' => 'Uloženka',
                'id_tax_rules_group' => 0,
                'active' => true,
                'deleted' => 0,
                'shipping_handling' => false,
                'shipping_method' => 2,
                'range_behavior' => 0,
                'delay' => array('cs' => 'Dva dny'),
                'grade' => 5,
                'is_module' => true,
                'shipping_external' => true,
                'external_module_name' => 'ulozenka',
                'need_range' => true
            )
        );
        if ($id_carrier = $install->installExternalCarrier($carrierConfig[0]))
            Configuration::updateValue($this->uppername . '_CARRIER_ID', $id_carrier);
        else
            return false;

        if (!$install->installModuleTab('AdminOrderUlozenka', 'Uloženka', 'AdminOrders'))
            return false;

        if (!$install->installSql())
            return false;

        if (!$install->addMailTemplate())
            return false;

        Configuration::updateValue($this->uppername . '_CARRIER_ID', (int) $id_carrier);
        Configuration::updateValue($this->uppername . '_LOGO_TYPE', 1);
        $this->registerHook('actionCarrierUpdate');
        $this->registerHook('displayBeforeCarrier');
        $this->registerHook('displayCarrierList');
        $this->registerHook('actionCarrierProcess');
        $this->registerHook('displayHeader');
        $this->registerHook('displayOrderConfirmation');





        $path = _PS_MODULE_DIR_ . $this->name . '/tpl/';
        if (is_writable($path)) {
            $version = self::getVersion();

            if ($version == 150)
                $source_file = 'form_154.tpl';
            elseif ($version == 155)
                $source_file = 'form_156.tpl';
            else // 160
                $source_file = 'form_16.tpl';



            if (file_exists($path . $source_file)) {
                $targetfile = $path . 'form.tpl';
                if (file_exists($targetfile))
                    unlink($targetfile);

                copy($path . $source_file, $targetfile);
            }
        }

        return true;
    }

    public function uninstall() {
        require_once(_PS_MODULE_DIR_ . $this->name . '/PrestahostModuleInstall.php');
        $install = new PrestahostModuleInstall($this);

        if (!$install->unistallExternalCarrier((int) Configuration::get($this->uppername . '_CARRIER_ID')))
            return false;


        if (!$install->uninstallSql())
            return false;

        if (!$install->uninstallModuleTab('AdminOrderUlozenka'))
            return false;

        $install->removeState('OS_ULOZENKA_DORUCENO');

        if (!parent::uninstall() ||
                !Configuration::deleteByName('ULOZENKA_ACCESS_CODE') ||
                !Configuration::deleteByName($this->uppername . '_SHIPPING_FREE') ||
                !Configuration::deleteByName($this->uppername . 'POBOCKY') ||
                !Configuration::deleteByName($this->uppername . 'POBOCKY_ALLOW') ||
                !Configuration::deleteByName('OS_ULOZENKA_DORUCENO') ||
                !Configuration::deleteByName($this->uppername . '_CARRIER_ID') ||
                !Configuration::deleteByName($this->uppername . '_DEFAULT_PRICE') ||
                !Configuration::deleteByName($this->uppername . '_LOGO_TYPE') ||
                !Configuration::deleteByName($this->uppername . '_COD_MODULES') ||
                !Configuration::deleteByName('ULOZENKA_API_KEY') ||
                !$this->unregisterHook('actionCarrierUpdate') ||
                !$this->unregisterHook('displayBeforeCarrier') ||
                !$this->unregisterHook('displayCarrierList') ||
                !$this->unregisterHook('actionCarrierProcess') ||
                !$this->unregisterHook('displayOrderConfirmation') ||
                !$this->unregisterHook('displayHeader')
        )
            return false;


        return true;
    }

    public function copyLogo($id_carrier, $typ = 1) {
        switch ($typ) {
            case 1: $sourceImg = 'ulozenka.jpg';
                break;
            case 2: $sourceImg = 'ulozenkask.jpg';
                break;
            case 3: {
                    $target = _PS_SHIP_IMG_DIR_ . (int) $id_carrier . '.jpg';
                    if (file_exists($target))
                        unlink($target);
                    $old_tmp_logo = _PS_TMP_IMG_DIR_ . '/carrier_mini_' . (int) $id_carrier . '.jpg';
                    if (file_exists($target))
                        unlink($target);
                    return;
                }
        }

        $source = _PS_MODULE_DIR_ . $this->name . '/' . $sourceImg;
        $target = _PS_SHIP_IMG_DIR_ . (int) $id_carrier . '.jpg';
        copy($source, $target);
    }

    public function getContent() {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $code = strval(Tools::getValue('ULOZENKA_ACCESS_CODE'));
            if (!$code || empty($code))
                $output .= $this->displayError($this->l('Invalid code value'));
            else {
                Configuration::updateValue('ULOZENKA_ACCESS_CODE', $code);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }


            Configuration::updateValue($this->uppername . '_API_KEY', Tools::getValue($this->uppername . '_API_KEY'));

            if (Tools::getValue($this->uppername . '_SHIPPING_FREE') !== false) {
                Configuration::updateValue($this->uppername . '_SHIPPING_FREE', Tools::getValue($this->uppername . '_SHIPPING_FREE'));
            }
            if (Tools::getValue('OS_ULOZENKA_DORUCENO') !== false) {
                Configuration::updateValue('OS_ULOZENKA_DORUCENO', Tools::getValue('OS_ULOZENKA_DORUCENO'));
            }


            if (Tools::getValue($this->uppername . '_DEFAULT_PRICE') !== false) {
                Configuration::updateValue($this->uppername . '_DEFAULT_PRICE', Tools::getValue($this->uppername . '_DEFAULT_PRICE'));
            }
            if (Tools::getValue($this->uppername . '_LOGO_TYPE') !== false) {

                if (!(int) Configuration::get($this->uppername . '_LOGO_TYPE') || (int) Configuration::get($this->uppername . '_LOGO_TYPE') != Tools::getValue($this->uppername . '_LOGO_TYPE')) {
                    Configuration::updateValue($this->uppername . '_LOGO_TYPE', Tools::getValue($this->uppername . '_LOGO_TYPE'));

                    $this->copyLogo(Configuration::get($this->uppername . '_CARRIER_ID'), Configuration::get($this->uppername . '_LOGO_TYPE'));
                }
            }
            if (Tools::getValue($this->uppername . '_SHOW_LIST') !== false) {
                Configuration::updateValue($this->uppername . '_SHOW_LIST', Tools::getValue($this->uppername . '_SHOW_LIST'));
            }

            if (Tools::getValue($this->uppername . '_COD_MODULES') !== false) {
                Configuration::updateValue($this->uppername . '_COD_MODULES', json_encode(Tools::getValue($this->uppername . '_COD_MODULES')));
            } else {
                Configuration::updateValue($this->uppername . '_COD_MODULES', '');
            }



            if (Tools::getValue($this->uppername . '_CSV_REF') !== false) {
                Configuration::updateValue($this->uppername . '_CSV_REF', Tools::getValue($this->uppername . '_CSV_REF'));
            } else {
                Configuration::updateValue($this->uppername . '_CSV_REF', 0);
            }


            Configuration::updateValue('ULOZENKA_POBOCKY', json_encode(Tools::getValue('ULOZENKA_POBOCKY')));
            Configuration::updateValue('ULOZENKA_POBOCKY_ALLOW', json_encode(Tools::getValue('ULOZENKA_POBOCKY_ALLOW')));
        }
        return $output . $this->displayForm();
    }

    public function displayForm() {
        // Get default Language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                ),
                'submit' => array(
                    'title' => $this->l('Uložit vše'),
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('ID shopu'),
                        'name' => 'ULOZENKA_ACCESS_CODE',
                        'size' => 20,
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API klíč'),
                        'name' => $this->uppername . '_API_KEY',
                        'size' => 28,
                        'required' => false
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Shipping free from'),
                        'name' => $this->uppername . '_SHIPPING_FREE',
                        'size' => 20,
                        'required' => false
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Default price'),
                        'name' => $this->uppername . '_DEFAULT_PRICE',
                        'size' => 20,
                        'required' => false
                    ),
                ),
                'custom_content' => '',
            ),
        );

        $helper = new HelperForm();

        // Module, t    oken and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;


        $helper->base_folder = _PS_MODULE_DIR_ . $this->name . '/tpl/';

        $helper->tpl_vars['custom_content'] = $this->displayPobockyAdmin();
        //  enable logo selector
        $helper->tpl_vars['custom_content'].='<br /><br />' . $this->displayLogoAdmin();

        $helper->tpl_vars['custom_content'].='<br /><br />' . $this->displayPaymentModules();

        $helper->tpl_vars['custom_content'].='<br /><br />' . $this->displayCommunicationInfo();

        $helper->tpl_vars['custom_content'].='<br /><br />' .
                '<center><input  value="Uložit vše" name="submitulozenka" type="submit"></center>';
        $helper->fields_value[$this->uppername . '_API_KEY'] = Configuration::get($this->uppername . '_API_KEY');
        $helper->fields_value[$this->uppername . '_ACCESS_CODE'] = Configuration::get($this->uppername . '_ACCESS_CODE');
        $helper->fields_value[$this->uppername . '_DEFAULT_PRICE'] = Configuration::get($this->uppername . '_DEFAULT_PRICE');
        $helper->fields_value[$this->uppername . '_SHIPPING_FREE'] = Configuration::get($this->uppername . '_SHIPPING_FREE');
        $helper->fields_value[$this->uppername . '_LOGO_TYPE'] = Configuration::get($this->uppername . '_LOGO_TYPE');
        $ret = $helper->generateForm(array($fields_form));
        return $ret;
    }

    protected function displayCommunicationInfo() {
        $retval = '<fieldset><legend>' . $this->l('Communication with ulozenka') . '</legend>';



        $sql = 'SELECT id_order_state, name  FROM ' . _DB_PREFIX_ . 'order_state_lang WHERE id_lang="' . Configuration::get('PS_LANG_DEFAULT') . '"';
        $states = Db::getInstance()->executeS($sql);
        $retval.=$this->l('Order status for delivered package') . ' ';
        $retval.='<select name="OS_ULOZENKA_DORUCENO"><option value="0">' . $this->l("Not selected") . '</option>';
        foreach ($states as $state) {
            $selected = Configuration::get("OS_ULOZENKA_DORUCENO") == $state['id_order_state'] ? " selected='selected'" : "";
            $retval.="<option value='{$state['id_order_state']}' $selected>{$state['name']}</option>";
        }
        $retval.='</select><br />';

        $retval.= $this->l('For automatically setting the delivered state ask your hosting provider to setup a cronjob') . ' <br />';
        $url = _PS_BASE_URL_ . Context::getContext()->shop->getBaseUri() . 'modules/' . $this->name . '/cron.php?code=' . substr(Configuration::get($this->uppername . '_ACCESS_CODE'), 0, 5);
        $retval.='<a href="' . $url . '">' . $url . '</a><br /><br />';

        $retval.='<b>' . $this->l('Exporting orders to ulozenka') . '</b><br />';
        $retval.=$this->l('Please create the csv file using the separate');
        $retval.=' <b><a href="' . Context::getContext()->link->getAdminLink('AdminOrderUlozenka') . '"/>' . $this->l('Ulozenka tab') . '</a></b>. <br />';
        $retval.=$this->l('Pro funkčnost je nutné správně zadat API klíč výše.') . '<br /><br />';


        $checked = Configuration::get($this->uppername . '_CSV_REF') ? " checked='checked'" : '';
        $retval.=$this->l('Use reference') . ' ';
        $retval.='<input type="checkbox" value="1" name="' . $this->uppername . '_CSV_REF' . '" ' . $checked . '><br />';
        $retval.=$this->l('Export order reference instead of ID (also apply for email messages)') . '<br />';

        $retval . '<br ></fieldset><br /><br />';
        return $retval;
    }

    protected function displayPaymentModules() {
        $modules = PaymentModule::getInstalledPaymentModules();
        $output = '<fieldset><legend>' . $this->l('Payment modules AND COD') . '</legend>';
        $output.='<table><tr><td>' . $this->l('Payment module') . '</td><td>' . $this->l('Is COD') . '</td></tr>';
        $codModules = Configuration::get($this->uppername . '_COD_MODULES');
        if ($codModules && strlen($codModules))
            $codModules = json_decode($codModules, true);
        foreach ($modules as $module) {

            $instance = Module::getInstanceByName($module['name']);
            if ($instance->active) {
                if (isset($codModules[$instance->id]))
                    $checked = " checked='checked'";
                else
                    $checked = "";

                $output.='<tr><td>' . $instance->displayName . '</td><td>
           <input type="checkbox" name="' . $this->uppername . '_COD_MODULES' . '[' . $instance->id . ']" ' . $checked . ' />
           </td></tr>';
            }
        }
        return $output . '</table></fieldset><br /><br />';
    }

    protected function displayLogoAdmin() {
        $images = array('ulozenka', 'ulozenkask', '');
        $values = array(1, 2, 3);
        $labels = array($this->l('CZ'), $this->l('SK'), $this->l('None'));

        $retval = '<fieldset><legend>' . $this->l('Logo') . '</legend>';
        $sel = (int) Configuration::get($this->uppername . '_LOGO_TYPE');

        foreach ($values as $value) {
            $selected = 0;
            if ($sel == $value)
                $selected = " checked='checked'";

            $retval.='<input name="ULOZENKA_LOGO_TYPE" id="" value="' . $value . '" ' . $selected . ' type="radio">';
            $retval.=$labels[$value - 1];
            if ($images[$value - 1]) {
                $img = _PS_BASE_URL_ . '/modules/' . $this->name . '/' . $images[$value - 1] . '.jpg';
                $retval.= '<img src=' . $img . ' /> |';
            }
        }

        $retval.='</fieldset><br /><br />';

        return $retval;
    }

    protected function displayPobockyAdmin() {
        $retval = '<fieldset><legend>' . $this->l('Ulozenka stores') . '</legend>';
        $code = Configuration::get('ULOZENKA_ACCESS_CODE');

        $pobocky = $this->getUlozenkaItems();
        $ceny = false;
        if (Configuration::get('ULOZENKA_POBOCKY')) {
            $ceny = json_decode(Configuration::get('ULOZENKA_POBOCKY'), true);
        }
        $allow = array();
        if (Configuration::get('ULOZENKA_POBOCKY_ALLOW')) {
            $allow = json_decode(Configuration::get('ULOZENKA_POBOCKY_ALLOW'), true);
        }

        $retval.='<table><tr><th>' . $this->l('Store') . '</th><th>' . $this->l('Price') . '</th><th>' . $this->l('Allowed') . '</th>';
        foreach ($pobocky as $pobocka) {
            if ($pobocka['active']) {
                $checked = $this->pobockaAllow($allow, $pobocka['shortcut']);
                $cena = $this->pobockaCena($ceny, $pobocka['shortcut']);
                $retval.='<tr><td>' . $pobocka['name'] . '</td><td><input type="text" name="ULOZENKA_POBOCKY[' . $pobocka['shortcut'] . ']" value="' . $cena . '" /></td><td><input type="checkbox" name="ULOZENKA_POBOCKY_ALLOW[' . $pobocka['shortcut'] . ']" value="1"  ' . $checked . '/></td></tr>';
            }
        }

        $retval.='</table><br />';

        $retval.='<b>' . $this->l('Show') . '</b> ';

        $values = array(1, 2);
        $labels = array($this->l('Only list'), $this->l('List and store details'));

        $sel = (int) Configuration::get($this->uppername . '_SHOW_LIST') ? (int) Configuration::get($this->uppername . '_SHOW_LIST') : 1;

        foreach ($values as $value) {
            $selected = 0;
            if ($sel == $value)
                $selected = " checked='checked'";

            $retval.='<input name="ULOZENKA_SHOW_LIST" id="" value="' . $value . '" ' . $selected . ' type="radio">';
            $retval.=$labels[$value - 1];

            $retval.= ' |';
        }



        $retval.='</fieldset><br /><br />';
        return $retval;
    }

    protected function getPobockyFront() {

        $code = Configuration::get('ULOZENKA_ACCESS_CODE');
        if (!$code || empty($code))
            return '';

        $ceny = false;
        if (Configuration::get('ULOZENKA_POBOCKY')) {
            $ceny = json_decode(Configuration::get('ULOZENKA_POBOCKY'), true);
        }
        if (Configuration::get('ULOZENKA_POBOCKY_ALLOW')) {
            $allow = json_decode(Configuration::get('ULOZENKA_POBOCKY_ALLOW'), true);
        }
        $retval = '<option value=\'\'>' . $this->l('Please select') . '</option>';
        $pobocky = $this->getUlozenkaItems();
        $selected = (isset(Context::getContext()->cookie->{$this->name}) && strlen(Context::getContext()->cookie->{$this->name})) ? Context::getContext()->cookie->{$this->name} : false;
        $zdarma = 0;

        if (self::isFreeShipping(Context::getContext()->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS)))
            $zdarma = 1;

        foreach ($pobocky as $pobocka) {
            if ($pobocka['active'] && $this->pobockaAllow($allow, $pobocka['shortcut'])) {
                //       $pobocka['mapa']=str_replace("style=","target='_blank'  style=",      $pobocka['mapa']);
                if ($zdarma == 1)
                    $cena = 0;
                else
                    $cena = $this->pobockaCena($ceny, $pobocka['shortcut']);

                if ($cena === '') {
                    $cena = $this->getDefaultPrice(false);
                }


                if ((float) $cena) {
                    $cena = $this->addTax($cena);
                }

                if ((float) $cena || $cena === 0 || $cena === '0')
                    $cena = Tools::displayPrice(Tools::convertPrice($cena));




                $isSelected = ($selected && $selected == $pobocka['shortcut']) ? " selected='selected'" : '';
                $this->context->smarty->assign(
                        array('pobocka' => $pobocka, 'cena' => $cena));
                $line = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/tpl/line.tpl');
                $retval.="<option value='{$pobocka['shortcut']}' $isSelected>$line</option>";
            }
        }
        return $retval;
    }

    protected function pobockaCena($ceny, $zkratka) {

        if (isset($ceny[$zkratka])) {
            return $ceny[$zkratka];
        }

        return '';
    }

    protected function pobockaAllow($allow, $zkratka) {
        $retval = '';
        if (isset($allow[$zkratka])) {
            $retval = " checked='checked'";
        }
        return $retval;
    }

    public function getUlozenkaItems() {

        $local = $source = _PS_MODULE_DIR_ . 'ulozenka/ulozenka.xml';   // do not use this->name

        if (!file_exists($local) || (time() - filemtime($local) > 3600)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_MAXCONNECTS, 10);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Accept: application/xml"));
            curl_setopt($ch, CURLOPT_URL, 'https://api.ulozenka.cz/v2/branches?shopId=' . Configuration::get('ULOZENKA_ACCESS_CODE'));
            $rawdata = curl_exec($ch);
            $info = curl_getinfo($ch);

            if ($info['http_code'] >= 200 || $info['http_code'] <= 299) {
                if ($rawdata && strlen($rawdata) > 1000) {
                    $isValidXml = 1;
                    libxml_use_internal_errors(true);
                    $sxe = simplexml_load_string($rawdata);
                    if (!$sxe) {
                        $isValidXml = 0;
                        foreach (libxml_get_errors() as $error) {
                            $isValidXml = 0;
                        }
                    }
                    libxml_use_internal_errors(true);
                    if ($isValidXml) {
                        if (file_exists($local)) {
                            unlink($local);
                        }
                        file_put_contents($local, $rawdata);
                    }
                }
            }
        }

        $xml = simplexml_load_file($local);
        foreach ($xml->branch as $pobocka)
            $retval[] = json_decode(json_encode($pobocka), true);

        return $retval;
    }

    public static function getItemsStatic() {
        return self::getUlozenkaItems();
    }

    protected function displayPobocky($params) {
        $num = 0;
        $retval = '';



        $num = $this->getCarrierOptionNum($params);
        $launch = '';
        if (!isset($params['checked']))
            $launch = "ulozenka();";

        $pobockstr = $this->getPobockyFront();

        $detail = '';
        if ((int) Configuration::get($this->uppername . '_SHOW_LIST') == 2) {

            $base = _PS_BASE_URL_ . Context::getContext()->shop->getBaseUri() . 'modules/' . $this->name . '/pobocka.php';
            $code = Context::getContext()->cookie->{$this->name};
            if (strlen($code)) {
                $urldetail = $base . '?code=' . $code;
                $detail.="<a  id='pobockadetail' href='$urldetail' target='_blank' onclick='fbox();return false'>" . $this->l('Store detail') . '</a>';
            } else {
                $urldetail = $base;
                $detail.="<a  id='pobockadetail' style='display:none' href='$urldetail' target='_blank' onclick='fbox();return false'>" . $this->l('Store detail') . '</a>';
            }
        }
        $ulozenkaSelectBox = "<span id='ulozenka'></span> &nbsp; <strong>Vyberte dodací místo: </strong><select name='ulozenka' style='border:1px solid #CCCCCC;margin-left:10px' id='selulozenka' onChange='selPobocka($num);'>$pobockstr</select>    $detail";



        $opc = (int) Configuration::get('PS_ORDER_PROCESS_TYPE');
        $options = Tools::unSerialize(Context::getContext()->cart->delivery_option, true);

        $ulozenkaActive = 0;
        if (is_array($options))
            foreach ($options as $id_address => $id_carrier) {
                if ((int) $id_carrier == Configuration::get('ULOZENKA_CARRIER_ID')) {
                    $ulozenkaActive = 1;
                }
            }
        $pobockaSelected = (isset(Context::getContext()->cookie->ulozenka) && strlen((Context::getContext()->cookie->ulozenka))) ? 1 : 0;

        $refresh = 0;
        $refreshed = '';
        if ($opc && !$pobockaSelected && $ulozenkaActive) {
            $refreshed = $this->ajax_getPaymentMethods();
            $refresh = 1;
        }
        $warning = $this->l("Please select a store");


        $targetClass = 'delivery_option';
        $version = self::getVersion();
        $onepageactive = 0;
        if (Module::isEnabled('onepagecheckout')) {
            $targetClass = 'carrier_infos';

            $onepageactive = 1;
        }


        $retval.= <<<ULOZENKA
\n
  <script language="JavaScript">
  <!--
var pobockaSelected;
var ulozenkaActive;
var onepageactive=$onepageactive;
window.onload=function(){
  ulozenka();
 
  
 if(onepageactive) {
  oldpaymentModuleConfirm= paymentModuleConfirm;
  paymentModuleConfirm = function() { 
  var cancontinue =onepage();   
   if ( cancontinue )
    oldpaymentModuleConfirm();
  }
 }
}
$launch
function ulozenka() {
 pobockaSelected  =$pobockaSelected;
 ulozenkaActive = $ulozenkaActive;
if($refresh) {
 $("#HOOK_PAYMENT").html('$refreshed');
}
 
if(document.getElementById("ulozenka") !== null)
return;

if(!$opc) {
  var form=  document.getElementById("form")
  if(form !== null)  {
  form.onsubmit = function( e ) {
   if(ulozenkaActive && !pobockaSelected) {
   e = e || window.event;
       e.preventDefault();
       alert('$warning');
       e.returnValue = false;
   }
   else
    return  acceptCGV();
};
  
}
}

 var test= $( ".$targetClass"  );
if($version == 160 && !onepageactive) {
 var mycell= $(test[$num]).find(".delivery_option_logo").next(); 
$(mycell).append( "$ulozenkaSelectBox" )
}else {
$(test[$num]).append( "$ulozenkaSelectBox" )

}
}
function onepage() {
      if(ulozenkaActive && ! pobockaSelected) {
        var errors = '<div   style="color:red">Prosím vyberte pobočku</div>';
        $('#opc_carrier_errors').html(errors).slideUp('fast').slideDown('slow');
        $.fn.scrollToElement('#opc_carrier_errors', 500);
        return false;
     }
     else {
         $('#opc_carrier_errors').html(errors).hide();
         return true;
     }
}
  //-->
  </script>
ULOZENKA;



        return $retval;
    }

    private function getCarrierOptionNum($params) {
        $carriers = Context::getContext()->cart->simulateCarriersOutput();
        if (isset($carriers[0]['mod']))
            return $this->getCarrierOptionNumFromOverriden($carriers);

        return $this->getCarrierOptionNumFromIntifier($carriers);
    }

    private function getCarrierOptionNumFromOverriden($carriers) {
        $num = 0;
        foreach ($carriers as $carrier) {
            if (isset($carrier['mod']) && $carrier['mod'] == 'ulozenka') {
                return $num;
            }
            $num++;
        }
        return $num;
    }

    private function getCarrierOptionNumFromIntifier($carriers) {
        $num = 0;
        foreach ($carriers as $carrier) {
            $test = Cart::intifier(Configuration::get($this->uppername . '_CARRIER_ID') . ',');
            if ($carrier['id_carrier'] == $test) {
                return $num;
            }
            $num++;
        }
        return $num;
    }

    public function hookActionCarrierUpdate($params) {
        // Update the id for carrier 1
        if ((int) ($params['id_carrier']) == (int) (Configuration::get($this->uppername . '_CARRIER_ID'))) {
            Configuration::updateValue($this->uppername . '_CARRIER_ID', (int) ($params['carrier']->id));
            $this->copyLogo((int) ($params['carrier']->id), $this->uppername . '_LOGO_TYPE');
        }
    }

    // 5 steps
    public function hookDisplayCarrierList($params) {

        return $this->displayPobocky($params);
    }

    // opc
    public function hookDisplayBeforeCarrier($params) {

        if (!isset($params['delivery_option_list'][0]))
            return;
        return $this->displayPobocky($params);
    }

    public function hookActionCarrierProcess($params) {
        return;
        // depraciated ... replaced by ajax
        if ((int) $params['cart']->id_carrier != (int) Configuration::get($this->uppername . '_CARRIER_ID'))
            return;
        Context::getContext()->cookie->{$this->name} = Tools::getValue($this->name);
    }

    public function hookDisplayOrderConfirmation($params) {

        if ($params['objOrder']->id_carrier != (int) Configuration::get($this->uppername . '_CARRIER_ID'))
            return;

        $code = Context::getContext()->cookie->{$this->name};
        $pobocka = $this->getPobockaByZkratka($code);

        $this->logCarrier($params, $pobocka);


        unset(Context::getContext()->cookie->{$this->name});
        $instance = Module::getInstanceByName($params['objOrder']->module);
        $codModules = Configuration::get($this->uppername . '_COD_MODULES');
        $dobirka = 0;
        if ($codModules && strlen($codModules)) {
            $codModules = json_decode($codModules, true);
            if (isset($codModules[$instance->id]))
                $dobirka = 1;
        }

        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'ulozenka SET
          id_order=' . (int) $params['objOrder']->id . ',
          pobocka="' . pSQL($pobocka['shortcut']) . '",
          dobirka="' . (int) $dobirka . '",
          pobocka_name="' . pSQL($pobocka['name']) . '"
        ';
        //  echo $sql;
        $provoz = '';
        foreach ($pobocka['openingHours']['regular'] as $day => $val) {
            $den = $this->translateDay($day);
            if ($val['hours']['open']) {
                $provoz.=$den . ': ' . $val['hours']['open'] . '-' . $val['hours']['close'] . ' <br />';
            }
        }
        $pobocka['provoz'] = $provoz;
        Db::getInstance()->execute($sql);
        $this->sendMail($pobocka, $params);
        return $this->displayPobocka($pobocka);
    }

    private function translateDay($day) {
        switch ($day) {
            case 'monday': return 'Po';
            case 'tuesday': return 'Út';
            case 'wednesday': return 'St';
            case 'thursday': return 'Čt';
            case 'friday': return 'Pá';
            case 'saturday': return 'So';
            case 'sunday': return 'Ne';
        }
    }

    public function getPobockaByZkratka($code, $use_default = 1) {

        $pobocky = $this->getUlozenkaItems();
        if ($code && strlen($code)) {
            foreach ($pobocky as $pobocka) {
                if ($pobocka['shortcut'] == $code) {
                    return $pobocka;
                }
            }
        }
        if ($use_default)
            return $pobocky[0];

        return false;
    }

    public function exportOrders($ids) {

        require_once(_PS_MODULE_DIR_ . $this->name . '/ApiData.php');
        require_once(_PS_MODULE_DIR_ . $this->name . '/UlozenkaApi.php');
        $ApiData = new ApiData();
        $Api = new UlozenkaApi();
        $data = $ApiData->getData($ids);

        $pobocky = $this->getUlozenkaItems();
        while (list($id, $line) = each($data)) {
            if ($line['exported'] == 1)
                continue;

            $line['branch'] = $this->getBranchFromShortcut($line['pobocka'], $pobocky);
            $id_ulozenka = $Api->getUlozenkaId($line);

            if ((int) $id_ulozenka) {
                $date = date('Y-m-d H:i:s');
                $sql = 'UPDATE ' . _DB_PREFIX_ . 'ulozenka SET exported=1, date_exp="' . $date . '", id_ulozenka=' . (int) $id_ulozenka . ' WHERE id_order=' . (int) $id;
                Db::getInstance()->execute($sql);
            }
        }
        return $Api->hasErrors();
    }

    protected function getBranchFromShortcut($shortcut, $pobocky) {
        foreach ($pobocky as $pobocka) {
            if ($pobocka['shortcut'] == $shortcut) {
                return $pobocka;
            }
        }

        return NULL;
    }

    public function displayPobocka($pobocka, $showtext = true) {


        $this->context->smarty->assign(
                array('pobocka' => $pobocka, 'showtext' => $showtext)
        );

        $retval = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/tpl/pobocka.tpl');



        return $retval;
    }

    protected function sendMail($pobocka, $params) {
        $selected_store = $pobocka['name'] . '<br />' .
                $pobocka['street'] . '<br />' .
                $pobocka['zip'] . ' ' . $pobocka['town'] . '<br />' .
                'Email: ' . $pobocka['email'] . '<br />' .
                'Tel. ' . $pobocka['phone'] . '<br />' .
                'Web. ' . $pobocka['link'] . '<br />' .
                'Otevřeno. ' . $pobocka['provoz'] . '<br />';

        $Customer = new Customer((int) $params['objOrder']->id_customer);


        $data = array(
            '{lastname}' => $Customer->lastname,
            '{id_order}' => (int) $params['objOrder']->id,
            '{selected_store}' => $selected_store,
            '{total_paid}' => $params['objOrder']->total_paid
        );
        if (Configuration::get($this->uppername . '_CSV_REF')) {
            $data['{id_order}'] = $params['objOrder']->reference;
        }
        $topic = $this->l('Ulozenka store address');

        /*  public static function Send($id_lang, $template, $subject, $template_vars, $to,
          $to_name = null, $from = null, $from_name = null, $file_attachment = null, $mode_smtp = null,
          $template_path = _PS_MAIL_DIR_, $die = false, $id_shop = null, $bcc = null)
         */
        Mail::Send((int) $params['objOrder']->id_lang, $this->name, $topic, $data, $Customer->email, $Customer->firstname . ' ' . $Customer->lastname, null, null, null, null, _PS_MAIL_DIR_, false, (int) $params['objOrder']->id_shop);
    }

    protected function logCarrier($params, $pobocka) {

        $message = $this->uppername . ': ' . strtoupper($pobocka['shortcut']) . ' ' . $pobocka['town'] . '  ' . $pobocka['street'];
        $Message = new Message();
        $Message->id_order = $params['objOrder']->id;
        $Message->message = $message;
        $Message->add();
    }

    public function ajax_getPaymentMethods() {
        $val = Context::getContext()->cookie->ulozenka;
        if (empty($val)) {
            return '<p class="warning">' . Tools::displayError('Prosím vyberte pobočku') . '</p>';
        }
        $isLogged = (bool) (Context::getContext()->customer->id && Customer::customerIdExistsStatic((int) (Context::getContext()->cookie->id_customer)));

        if (!$isLogged)
            return '<p class="warning">' . Tools::displayError('Please sign in to see payment methods.') . '</p>';
        if (Context::getContext()->cart->OrderExists())
            return '<p class="warning">' . Tools::displayError('Error: This order has already been validated.') . '</p>';
        if (!Context::getContext()->cart->id_customer || !Customer::customerIdExistsStatic(Context::getContext()->cart->id_customer) || Customer::isBanned(Context::getContext()->cart->id_customer))
            return '<p class="warning">' . Tools::displayError('Error: No customer.') . '</p>';
        $address_delivery = new Address(Context::getContext()->cart->id_address_delivery);
        $address_invoice = (Context::getContext()->cart->id_address_delivery == Context::getContext()->cart->id_address_invoice ? $address_delivery : new Address(Context::getContext()->cart->id_address_invoice));
        if (!Context::getContext()->cart->id_address_delivery || !Context::getContext()->cart->id_address_invoice || !Validate::isLoadedObject($address_delivery) || !Validate::isLoadedObject($address_invoice) || $address_invoice->deleted || $address_delivery->deleted)
            return '<p class="warning">' . Tools::displayError('Error: Please select an address.') . '</p>';
        /*
          if (count(Context::getContext()->cart->getDeliveryOptionList()) == 0 && !Context::getContext()->cart->isVirtualCart())
          {
          if (Context::getContext()->cart->isMultiAddressDelivery())
          return '<p class="warning">'.Tools::displayError('Error: None of your chosen carriers deliver to some of  the addresses you\'ve selected.').'</p>';
          else
          return '<p class="warning">'.Tools::displayError('Error: None of your chosen carriers deliver to the address you\'ve selected.').'</p>';
          }

          if (!Context::getContext()->cart->getDeliveryOption(null, false) && !Context::getContext()->cart->isVirtualCart())
          return '<p class="warning">'.Tools::displayError('Error: Please choose a carrier.').'</p>';
         */
        if (!Context::getContext()->cart->id_currency)
            return '<p class="warning">' . Tools::displayError('Error: No currency has been selected.') . '</p>';
        if (!Context::getContext()->cookie->checkedTOS && Configuration::get('PS_CONDITIONS'))
            return '<p class="warning">' . Tools::displayError('Please accept the Terms of Service.') . '</p>';

        /* If some products have disappear */
        if (!Context::getContext()->cart->checkQuantities())
            return '<p class="warning">' . Tools::displayError('An item in your cart is no longer available. You cannot proceed with your order.') . '</p>';

        /* Check minimal amount */
        $currency = Currency::getCurrency((int) Context::getContext()->cart->id_currency);

        $minimalPurchase = Tools::convertPrice((float) Configuration::get('PS_PURCHASE_MINIMUM'), $currency);
        if (Context::getContext()->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS) < $minimalPurchase)
            return '<p class="warning">' . sprintf(
                            Tools::displayError('A minimum purchase total of %s is required in order to validate your order.'), Tools::displayPrice($minimalPurchase, $currency)
                    ) . '</p>';

        /* Bypass payment step if total is 0 */
        if (Context::getContext()->cart->getOrderTotal() <= 0)
            return '<p class="center"><input type="button" class="exclusive_large" name="confirmOrder" id="confirmOrder" value="' . Tools::displayError('I confirm my order.') . '" onclick="confirmFreeOrder();" /></p>';

        $return = Hook::exec('displayPayment');
        if (!$return)
            return '<p class="warning">' . Tools::displayError('No payment method is available for use at this time. ') . '</p>';
        return $return;
    }

    public static function getVersion() {
        if (Tools::version_compare(_PS_VERSION_, '1.5.6.2', '>'))
            return 160;
        elseif (Tools::version_compare(_PS_VERSION_, '1.5.5', '>'))
            return 156;
        else
            return 150;
    }

    public function addTax($cena) {
        if (Configuration::get('PS_TAX')) {
            if ($this->tax_rate === false) {
                $this->taxrate = Tax::getCarrierTaxRate(Configuration::get($this->uppername . '_CARRIER_ID'));
            }
            $cena = $cena / 100 * (100 + $this->taxrate);
        }
        return $cena;
    }

}

?>