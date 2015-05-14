<?php
include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');

$codeConf = substr(Configuration::get('ULOZENKA_ACCESS_CODE'), 0, 5);
$codeGet = Tools::getValue('code');

if (empty($codeConf) || $codeConf != $codeGet) {
    //die();
}

if (!$id_order_state = Configuration::get("OS_ULOZENKA_DORUCENO"))
    return;

    const API_URI = 'https://partner.ulozenka.cz';
$uri = API_URI . '/v3/consignments';


$shopId = Configuration::get('ULOZENKA_ACCESS_CODE');
$apiKey = Configuration::get('ULOZENKA_API_KEY');


$sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'ulozenka WHERE id_ulozenka > 0 AND (doruceno = 0 OR doruceno is null)';
$zasilky = Db::getInstance()->executeS($sql);

$stavy = array('zásilka byla vydána');

$headers = array(
    'X-Shop: ' . $shopId,
    'X-Key: ' . $apiKey,
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true);


foreach ($zasilky as $zasilka) {
    $uri = API_URI . '/v3/consignments/' . $zasilka['id_ulozenka'] . '/statuses';

    curl_setopt($ch, CURLOPT_URL, $uri);


    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);


    if ($error) {
        // Zalogovat $error!
        logError('cron curl', $error);
    }

    $responseData = json_decode($body);
    $data = $responseData->data;
    if ($responseData->code < 200 || $responseData->code > 299) {
        //Je nutné zalogovat $responseData;
        //print_r($responseData);
        logError('cron response', var_export($responseData, true));
    } else {
        // Zde jsou stavy zásilky

        foreach ($responseData->data as $stav) {

            if (in_array($stav->name, $stavy)) {
                $sql = 'SELECT count(*) 
                                FROM ' . _DB_PREFIX_ . 'order_history WHERE id_order=' . (int) $zasilka['id_order'] . ' AND
                                id_order_state=' . (int) $id_order_state;
                if (!(int) Db::getInstance()->getValue($sql)) {
                    $sql = 'SELECT id_order FROM  ' . _DB_PREFIX_ . 'orders
                                          WHERE id_order=' . (int) $zasilka['id_order'];

                    if ($id_order = Db::getInstance()->getValue($sql)) {
                        $history = new OrderHistory();
                        $history->id_order = (int) $id_order;
                        $history->changeIdOrderState(Configuration::get('OS_ULOZENKA_DORUCENO'), $id_order, true);


                        $sql = 'UPDATE ' . _DB_PREFIX_ . 'ulozenka SET doruceno=1 WHERE id_order=' . (int) $zasilka['id_order'];
                        Db::getInstance()->execute($sql);
                    }
                }
                break;
            }
        }
    }
}

curl_close($ch);
return;

function logError($title, $error) {
    $path = _PS_MODULE_DIR_ . 'ulozenka/log.txt';
    if (file_exists($path) && filesize($path) > 10000000)// log too big
        return;

    $logfile = fopen($path, 'a+');
    fputs($logfile, date('d.m.Y. H:i:s') . ' ' . $title . "\n" . $error . "\n");
    fclose($logfile);
}

?>
