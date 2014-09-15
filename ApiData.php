<?php

class ApiData {

    public function getData($ids) {
        $retval = array();




        if ((int) Configuration::get("ULOZENKA_CSV_REF"))
            $reffile = 'reference';
        else
            $reffile = 'id_order';
        foreach ($ids as $id) {
            //IF(u.dobirka > 0, o.total_paid, 0) as cash_on_delivery
            $sql = 'SELECT 
             o.' . $reffile . ' as order_number,
             u.pobocka,
             u.exported,
             "1" as parcel_count,
             ad.address1 as    address_street,
             ad.city as    address_town,
             ad.postcode as    address_zip,
             c.firstname as customer_name, 
             c.lastname as  customer_surname,
             ad.company as  customer_company,
             IF(u.dobirka > 0, o.total_paid, 0) as cash_on_delivery,
          
             COALESCE(ad.phone_mobile, ai.phone_mobile,  ad.phone, ai.phone)  as customer_phone,    
             c.email as customer_email
            FROM ' . _DB_PREFIX_ . 'orders o
             LEFT JOIN   ' . _DB_PREFIX_ . 'customer c ON
             o.id_customer =c.id_customer
             LEFT  JOIN   ' . _DB_PREFIX_ . 'address ad ON
             o.id_address_delivery =ad.id_address
             LEFT  JOIN   ' . _DB_PREFIX_ . 'address ai ON
             o.id_address_invoice =ai.id_address
            LEFT  JOIN   ' . _DB_PREFIX_ . 'ulozenka u ON
             o.id_order =u.id_order 
             WHERE
            o.id_order=' . (int) $id;
            $data = Db::getInstance()->getRow($sql);
            if (empty($data['customer_phone'])) {   // coalesce  prijme prazdny retezec
                $data['customer_phone'] = $this->getPhone($id);
            }

            if ($data && is_array($data))
                $retval[$id] = $data;
        }
        return $retval;
    }

    private function getPhone($id_order) {
        $order = new Order($id_order);
        $address = new Address($order->id_address_delivery);
        if (strlen(trim($address->phone_mobile)))
            return trim($address->phone_mobile);
        if (strlen(trim($address->phone)))
            return trim($address->phone);

        $address = new Address($order->id_address_invoice);
        if (strlen(trim($address->phone_mobile)))
            return trim($address->phone_mobile);
        if (strlen(trim($address->phone)))
            return trim($address->phone);
    }

}

?>
