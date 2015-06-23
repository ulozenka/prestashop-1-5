<?php

class UlozenkaApi {

    protected $shopId;
    protected $apiKey;
    protected $cUrl;
    protected $errors;

    const API_URI = 'https://partner.ulozenka.cz';
    const ULOZENKA_TRANSPORT_SERVICE_ID = 1;
    const DPD_PARCELSHOP_TRANSPORT_SERVICE_ID = 5;
    const CURRENCY_CZK = "CZK";
    const CURRENCY_EUR = "EUR";
    const API_ID =  "3Q3sl8gU7r";
    const API_VERSION = "1.4.1";
    

    public function __construct() {

        $this->shopId = Configuration::get('ULOZENKA_ACCESS_CODE');
        $this->apiKey = Configuration::get('ULOZENKA_API_KEY');
        //    $this->apiKey ='zmLfMrMnfIYmPKQFSePibXWVG'; 

        $headers = array(
            'X-Shop: ' . $this->shopId,
            'X-Key: ' . $this->apiKey,
            'X-App-Id:' . self::API_ID,
            'X-App-Version:' . self::API_VERSION
        );

        $uri = self::API_URI . '/v3/consignments';
        $this->cUrl = curl_init();
        curl_setopt($this->cUrl, CURLOPT_URL, $uri);
        curl_setopt($this->cUrl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->cUrl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->cUrl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->cUrl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->cUrl, CURLOPT_HEADER, true);
        curl_setopt($this->cUrl, CURLOPT_CUSTOMREQUEST, "POST");
    }

    public function getUlozenkaId($jsonData) {
        //print_r($jsonData);

        if ($jsonData['branch']['partner'] == 0) {
            $jsonData['transport_service_id'] = self::ULOZENKA_TRANSPORT_SERVICE_ID; // Uloženka
        } else {
            $jsonData['transport_service_id'] = self::DPD_PARCELSHOP_TRANSPORT_SERVICE_ID; // DPD Parcel Shop
        }

        if ($jsonData['branch']['country'] == "SVK") {
            if ($jsonData['currency'] != self::CURRENCY_EUR) {
                $this->errors[] = 'Export se nezdařil. Na Slovensku nelze vybrat dobírku v jiné měně než v EUR.';
                return NULL;
            }
        } else {
            if ($jsonData['currency'] != self::CURRENCY_CZK) {
                $this->errors[] = 'Export se nezdařil. V České republice nelze vybrat dobírku v jiné měně než v CZK.';
                return NULL;
            }
        }

        if ($jsonData['cash_on_delivery'] > 0 && $jsonData['currency'] == "CZK") {
            $jsonData['cash_on_delivery'] = round($jsonData['cash_on_delivery']);
        }

        $jsonData['address_state'] = $jsonData['branch']['country'];
        $jsonData['destination_branch_id'] = $jsonData['branch']['id'];

        $content = json_encode($jsonData);
        curl_setopt($this->cUrl, CURLOPT_POSTFIELDS, $content);

        $response = curl_exec($this->cUrl);
        $header_size = curl_getinfo($this->cUrl, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $code = curl_getinfo($this->cUrl, CURLINFO_HTTP_CODE);
        $error = curl_error($this->cUrl);

        if ($error) {
            $this->logError('UlozenkaAPI chyba curl', var_export($jsonData, true));
            $this->logError('UlozenkaAPI chyba curl', $error);
            $this->errors[] = 'Export se nezdařil' . $error;
            return NULL;
        }
//echo $header;
//echo "================================================================================\n";
//echo $body;
//echo "\n";
        $responseData = json_decode($body);
        $data = $responseData->data;
        if ($responseData->code < 200 || $responseData->code > 299) {
            //Je nutné zalogovat $responseData;
            $this->logError('UlozenkaAPI chyba response', var_export($jsonData, true));
            $this->logError('UlozenkaAPI chyba response', var_export($responseData, true));
            $this->errors[] = 'Export se nezdařil' . var_export($responseData, true);
            return NULL;
        } else {
            $id = $data[0]->id; // TOTO JE ID ZÁSILKY -> ULOŽIT DO DATABÁZE (PODLE NĚJ BUDEME STAHOVAT STAVY!)
            return (int) $id;
        }
    }

    public function hasErrors() {
        //  $this->errors[]='test error';
        if (is_array($this->errors) && $this->errors) {
            return $this->errors;
        }
        return false;
    }

    public function __destruct() {
        if ($this->cUrl)
            curl_close($this->cUrl);
    }

    protected function logError($title, $error) {
        $path = _PS_MODULE_DIR_ . 'ulozenka/log.txt';
        if (file_exists($path) && filesize($path) > 10000000)// log too big
            return;

        $logfile = fopen($path, 'a+');
        fputs($logfile, date('d.m.Y. H:i:s') . ' ' . $title . "\n" . $error . "\n");
        fclose($logfile);
    }

}

?>
