<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Software License Agreement
 * that is bundled with this package in the file LICENSE.txt.
 * 
 *  @author    Peter Sliacky
 *  @copyright 2009-2014 Peter Sliacky
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0) 
 */
class Cart extends CartCore {

   

    /**
     * Get all deliveries options available for the current cart formated like Carriers::getCarriersForOrder
     * This method was wrote for retrocompatibility with 1.4 theme
     * New theme need to use Cart::getDeliveryOptionList() to generate carriers option in the checkout process
     *
     * @since 1.5.0
     *
     * @param Country $default_country
     * @param boolean $flush Force flushing cache
     *
     */
    public function simulateCarriersOutput(Country $default_country = null, $flush = false)
    {
        static $cache = false;
        if ($cache !== false && !$flush)
            return $cache;

        $delivery_option_list = $this->getDeliveryOptionList($default_country, $flush);

        // This method cannot work if there is multiple address delivery
        if (count($delivery_option_list) > 1 || empty($delivery_option_list))
            return array();

        $carriers = array();
        foreach (reset($delivery_option_list) as $key => $option)
        {
            $price = $option['total_price_with_tax'];
            $price_tax_exc = $option['total_price_without_tax'];

            if ($option['unique_carrier'])
            {
                $carrier = reset($option['carrier_list']);
                $name = $carrier['instance']->name;
                $img = $carrier['logo'];
                $delay = $carrier['instance']->delay;
                $delay = isset($delay[Context::getContext()->language->id]) ? $delay[Context::getContext()->language->id] : $delay[(int)Configuration::get('PS_LANG_DEFAULT')];
            }
            else
            {
                $nameList = array();
                foreach ($option['carrier_list'] as $carrier)
                    $nameList[] = $carrier['instance']->name;
                $name = join(' -', $nameList);
                $img = ''; // No images if multiple carriers
                $delay = '';
            }
            $mod=$carrier['instance']->external_module_name;
            $carriers[] = array(
                'name' => $name,
                'img' => $img,
                'mod'=>$mod,
                'delay' => $delay,
                'price' => $price,
                'price_tax_exc' => $price_tax_exc,
                'id_carrier' => Cart::intifier($key), // Need to translate to an integer for retrocompatibility reason, in 1.4 template we used intval
                'is_module' => false,
            );
        }
        return $carriers;
    }
}
