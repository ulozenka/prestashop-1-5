<?php

class PrestahostModuleInstall {

    protected $module;
    protected $messages = array();

    public function __construct($module) {
        $this->module = $module;
    }

    public function addState($statename, $translations, $color = '#FFC3C3', $email = 0, $logable = 0) {

        $values = array(
            'id_order_state' => null,
            'invoice' => 1,
            'send_email' => $email,
            'color' => $color,
            'unremovable' => 0,
            'logable' => $logable
        );
        Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'order_state', $values, 'INSERT');

        $lastid = Db::getInstance()->Insert_ID();
        if ($lastid) {
            Configuration::updateValue($statename, $lastid, 0, 0);
            $langs = Context::getContext()->language->getLanguages(true);
            foreach ($langs as $lang) {
                $name = isset($translations[$lang['iso_code']]) ? $translations[$lang['iso_code']] : $translations[$lang['en']];
                $values = array(
                    'id_order_state' => $lastid,
                    'id_lang' => $lang['id_lang'],
                    'name' => pSQL($name),
                    'template' => pSQL($this->module->name),
                );
                if (!Db::getInstance()->AutoExecute(_DB_PREFIX_ . 'order_state_lang', $values, 'INSERT')) {
                    $this->messages[] = 'Failed to insert  ' . $statename . ' into ' . _DB_PREFIX_ . 'order_state_lang';
                    return false;
                }
            }

            return true;
        } else
            $this->messages[] = 'Failed to insert  ' . $statename . ' into ' . _DB_PREFIX_ . 'order_state';
        return false;
    }

    public function removeState($statename) {
        if ($id_state = Configuration::get($statename)) {
            $state = new OrderState($id_state);
            $state->delete();
        }
    }

    public function installExternalCarrier($config) {
        $carrier = new Carrier();
        $carrier->name = $config['name'];
        $carrier->id_tax_rules_group = $config['id_tax_rules_group'];

        $carrier->active = $config['active'];
        $carrier->deleted = $config['deleted'];
        $carrier->delay = $config['delay'];
        $carrier->shipping_handling = $config['shipping_handling'];
        $carrier->range_behavior = $config['range_behavior'];
        $carrier->is_module = $config['is_module'];
        $carrier->shipping_external = $config['shipping_external'];
        $carrier->external_module_name = $config['external_module_name'];
        $carrier->need_range = $config['need_range'];

        $languages = Context::getContext()->language->getLanguages(true);
        foreach ($languages as $language) {
            if ($language['iso_code'] == 'cs')
                $carrier->delay[(int) $language['id_lang']] = $config['delay'][$language['iso_code']];
            else
                $carrier->delay[(int) $language['id_lang']] = '2 days';
        }

        if ($carrier->add()) {
            $groups = Group::getGroups(true);
            foreach ($groups as $group)
                Db::getInstance()->autoExecute(_DB_PREFIX_ . 'carrier_group', array('id_carrier' => (int) ($carrier->id), 'id_group' => (int) ($group['id_group'])), 'INSERT');

            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '1000000';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '10000';
            $rangeWeight->add();
            $sql = 'SELECT DISTINCT id_zone FROM ' . _DB_PREFIX_ . 'country WHERE iso_code="CZ" OR iso_code="SK"';
            $zones = Db::getInstance()->executeS($sql);
            if (is_array($zones)) {
                foreach ($zones as $zone) {
                    Db::getInstance()->autoExecute(_DB_PREFIX_ . 'carrier_zone', array('id_carrier' => (int) ($carrier->id), 'id_zone' => (int) ($zone['id_zone'])), 'INSERT');
                    Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery', array('id_carrier' => (int) ($carrier->id), 'id_range_price' => (int) ($rangePrice->id), 'id_range_weight' => NULL, 'id_zone' => (int) ($zone['id_zone']), 'price' => '0'), 'INSERT');
                    Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery', array('id_carrier' => (int) ($carrier->id), 'id_range_price' => NULL, 'id_range_weight' => (int) ($rangeWeight->id), 'id_zone' => (int) ($zone['id_zone']), 'price' => '0'), 'INSERT');
                }
            }

            $this->module->copyLogo($carrier->id);


            if (Configuration::get('PS_TAX')) {
                $carrier->setTaxRulesGroup(1, true);
            }
            return (int) ($carrier->id);
        }
        $this->messages[] = 'Failed to create external carrier ' . $config['name'];

        return false;
    }

    public function unistallExternalCarrier($id_carrier) {
        if ((int) $id_carrier) {
            $Carrier1 = new Carrier((int) ($id_carrier));

            // If external carrier is default set other one as default
            if (Configuration::get('PS_CARRIER_DEFAULT') == (int) ($Carrier1->id)) {
                $this->module->_errors[] = 'Please select different default carrier before uninstall';
                return false;
            }
            // Then delete Carrier
            $Carrier1->deleted = 1;
            if (!$Carrier1->update())
                return false;
        }
        return true;
    }

    public function installModuleTab($tabClass, $tabName, $parentName) {
        $sql = 'SELECT id_tab FROM ' . _DB_PREFIX_ . 'tab WHERE class_name="' . pSQL($parentName) . '"';
        $idTabParent = Db::getInstance()->getValue($sql);
        if (!$idTabParent) {
            $this->messages[] = 'Failed to find parent tab ' . $parentName;
            return false;
        }

        @copy(_PS_MODULE_DIR_ . $this->module->name . '/logo.gif', _PS_IMG_DIR_ . 't/' . $tabClass . '.gif');
        $tab = new Tab();
        $tabNames = array();
        foreach (Language::getLanguages(false) as $language) {
            $tabNames[$language['id_lang']] = $tabName;
        }
        $tab->name = $tabNames;
        $tab->class_name = $tabClass;
        $tab->module = $this->module->name;
        $tab->id_parent = $idTabParent;
        if (!$tab->save()) {
            $this->messages[] = 'Failed save Tab ' . implode(',', $tabNames);
            return false;
        }
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'access WHERE id_tab=' . (int) $tab->id;
        Db::getInstance()->execute($sql);
        if (!Tab::initAccess($tab->id)) {
            $this->messages[] = 'Failed save init access ' . implode(',', $tabNames);
            return false;
        }
        return true;
    }

    public function uninstallModuleTab($tabClass) {
        $idTab = Tab::getIdFromClassName($tabClass);
        if ($idTab != 0) {
            $tab = new Tab($idTab);
            $tab->delete();
            return true;
        }
        return true; // true even on failed
    }

    public function installSql() {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "ulozenka` (
  `id_order` int(10) unsigned NOT NULL DEFAULT '0',
   `id_ulozenka` int(10) unsigned NOT NULL DEFAULT '0',
  `dobirka` tinyint(3) unsigned DEFAULT '0',
  `date_exp` date DEFAULT NULL,
  `pobocka` varchar(25) COLLATE utf8_czech_ci DEFAULT NULL,
  `pobocka_name` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL,
  `exported` tinyint(3) unsigned DEFAULT '0',
   `doruceno` tinyint(3) unsigned DEFAULT '0',
  PRIMARY KEY (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci";

        $retval = Db::getInstance()->execute($sql);
        if (!$retval) {
            $this->messages[] = 'FAILED TO CREATE table `"' . _DB_PREFIX_ . '"ulozenka` )' . '  <br />' . $sql;
        }
        return $retval;
    }

    public function uninstallSql() {
        $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "ulozenka`";

        $retval = Db::getInstance()->execute($sql);
        return true; // even on failure
    }

    public function addMailTemplate() {

        $languages = Context::getContext()->language->getLanguages(true);
        $defaultdir = _PS_MODULE_DIR_ . $this->module->name . '/mails/' . Language::getIsoById(Configuration::get('PS_LANG_DEFAULT'));
        $success = true;
        foreach ($languages as $lan) {
            $dir = _PS_MODULE_DIR_ . $this->module->name . '/mails/' . $lan['iso_code'];


            if (file_exists($dir) && is_dir($dir)) {
                if (!$this->copyMailTemplate($dir . '/', _PS_MAIL_DIR_ . $lan['iso_code']))
                    $success = false;
            }
            elseif (file_exists($defaultdir) && is_dir($defaultdir)) {
                if (!$this->copyMailTemplate($defaultdir . '/', _PS_MAIL_DIR_ . $lan['iso_code']))
                    $success = false;
            }
            elseif (file_exists(_PS_MODULE_DIR_ . $this->module->name . '/mails/en/') && is_dir(_PS_MODULE_DIR_ . $this->module->name . '/mails/en/')) {
                if (!$this->copyMailTemplate(_PS_MODULE_DIR_ . $this->module->name . '/mails/en/', _PS_MAIL_DIR_ . $lan['iso_code']))
                    $success = false;
            }
            else {
                $this->messages[] = 'could not copy mail templates';
                return false;
            }
        }
        return $success;
    }

    private function copyMailTemplate($source, $maildir) {


        if (!is_writable($maildir)) {
            $this->messages[] = $maildir . ' not writable';
            return false;
        }

        if (!file_exists($source . $this->module->name . '.html')) {
            $this->messages[] = $source . $this->module->name . '.html does not exist';
            return false;
        }

        if (!file_exists($source . $this->module->name . '.txt')) {
            $this->messages[] = $source . $this->module->name . '.txt does not exist';
            return false;
        }

        if (!copy($source . $this->module->name . '.html', $maildir . '/' . $this->module->name . '.html') || !copy($source . $this->module->name . '.txt', $maildir . '/' . $this->module->name . '.txt')) {
            $this->messages[] = 'Failed to copy email templates from ' . $dir . ' to ' . $maildir;
            return false;
        }

        return true;
    }

    public function __destruct() {
        if (is_array($this->messages) && count($this->messages)) {
            while (list($key, $val) = each($this->messages))
                echo $val . '<br />';
        }
    }

}

?>
