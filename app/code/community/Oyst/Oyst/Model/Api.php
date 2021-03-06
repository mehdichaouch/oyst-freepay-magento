<?php
/**
 * This file is part of Oyst_Oyst for Magento.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @author Oyst <dev@oyst.com> <@oystcompany>
 * @category Oyst
 * @package Oyst_Oyst
 * @copyright Copyright (c) 2017 Oyst (http://www.oyst.com)
 */

use Oyst\Api\OystApiClientFactory;
use Oyst\Api\OystPaymentApi;
use Oyst\Classes\OystPrice;

/**
 * API Model
 */
class Oyst_Oyst_Model_Api extends Mage_Core_Model_Abstract
{
    /*
     * API length
     *
     * @var int
     */
    const API_KEY_LENGTH = 64;
    
    /**
     * API type of call
     *
     * @var string
     */
    const TYPE_POSTCATALOG = '_postcatalog';

    /**
     * API type of call
     *
     * @var string
     */
    const TYPE_PUTCATALOG = '_putcatalog';

    /**
     * API type of call
     *
     * @var string
     */
    const TYPE_GETORDER = '_getorder';

    /**
     * API type of call
     *
     * @var string
     */
    const TYPE_PUTORDER = '_putorder';

    /**
     * API type of call
     *
     * @var string
     */
    const TYPE_PAYMENT = OystApiClientFactory::ENTITY_PAYMENT;

    const CURRENCY = 'EUR';

    /**
     * Validade API key
     *
     * @param string $apiKey
     *
     * @return array
     */
    public function validateApikey($apiKey)
    {
        if (strlen($apiKey) === self::API_KEY_LENGTH) {
            return true;
        }

        return false;
    }

    /**
     * API call to Oyst
     *
     * @param string $type
     * @param array $dataFormated
     *
     * @return array
     */
    public function send($type, $dataFormated)
    {
        /** @var Oyst_Oyst_Helper_Data $oystHelper */
        $oystHelper = Mage::helper('oyst_oyst');

        //if api type don't have method associate
        if (!method_exists($this, $type)) {
            $oystHelper->log('Something wrong with Oyst api : ' . $type);
            Mage::throwException($this->__('Something wrong with Oyst api : ' . $type));
        }

        //get api service url from config
        $targetUrl = $this->_getConfig('api_url');

        //get api key from config
        $apiKey = $this->_getConfig('api_login');

        //if type is order, we must retrieve the param 'oyst_order_id' from $dataFormated but not send it
        if ($type == Oyst_Oyst_Model_Api::TYPE_PUTORDER) {
            $targetUrl .= 'order' . DS . 'orders' . DS . $dataFormated['oyst_order_id'];
            $oystHelper->log('Oyst api order id : ' . $dataFormated['oyst_order_id']);
            unset($dataFormated['oyst_order_id']);
        }

        $dataJson = Zend_Json::encode($dataFormated);

        // @codingStandardsIgnoreStart
        //init curl params according to method
        $ch = curl_init();

        //set curl opt, construct final $targetUrl and build Headers
        $headers = $this->$type($ch, $targetUrl, $apiKey, $dataJson);

        //set common curl opt
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // curl_setopt($ch, CURLOPT_HEADER, true);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);

        $res = curl_exec($ch);
        $info = curl_getinfo($ch);
        $oystHelper->log($dataJson);

        //analyse API response
        $resultArray = array();
        if ($res === false || $info['http_code'] != '200') {
            $oystHelper->log('Curl error target: ' . curl_error($ch));
            curl_close($ch);
            Mage::throwException($this->__('Curl error target: ' . curl_error($ch)));
        }

        $resultArray = Zend_Json::decode($res);

        curl_close($ch);
        // @codingStandardsIgnoreEnd

        return $resultArray;
    }

    /**
     * API call to Oyst Payment
     *
     * @param string $type
     * @param array $dataFormated
     *
     * @return OystPaymentAPI
     */
    public function sendPayment($type, $dataFormated)
    {
        $apiKey = $this->_getConfig('api_login');
        $userAgent = $this->getUserAgent();
        $env = $this->_getConfig('mode');
        $url = $this->getCustomApiUrl();

        /** @var OystPaymentAPI $oystClient */
        $oystClient = OystApiClientFactory::getClient($type, $apiKey, $userAgent, $env, $url);

        $oystClient->$type(
            $dataFormated['amount']['value'],
            $dataFormated['amount']['currency'],
            $dataFormated['order_id'],
            $dataFormated['urls'],
            false,
            $dataFormated['user']
        );

        return $oystClient;
    }

    /**
     * set curl opt, construct final $targetUrl and build Headers for POST catalog
     *
     * @param Resource $ch
     * @param string $targetUrl
     * @param string $apiKey
     * @param string $dataJson
     *
     * @return array
     */
    protected function _postcatalog(&$ch, &$targetUrl, $apiKey, $dataJson)
    {
        $targetUrl .= 'catalog' . DS . 'products';
        $this->_initCh($ch, 'POST', $dataJson);

        return $this->_getHeaders($apiKey, $dataJson);
    }

    /**
     * set curl opt, construct final $targetUrl and build Headers for PUT catalog
     *
     * @param Resource $ch
     * @param string $targetUrl
     * @param string $apiKey
     * @param string $dataJson
     *
     * @return array
     */
    protected function _putcatalog(&$ch, &$targetUrl, $apiKey, $dataJson)
    {
        $targetUrl .= 'catalog' . DS . 'products';
        $this->_initCh($ch, 'PUT', $dataJson);

        return $this->_getHeaders($apiKey, $dataJson);
    }

    /**
     * set curl opt, construct final $targetUrl and build Headers for GET order
     *
     * @param Resource $ch
     * @param string $targetUrl
     * @param string $apiKey
     * @param string $dataJson
     *
     * @return array
     */
    protected function _getorder(&$ch, &$targetUrl, $apiKey, $dataJson = false)
    {
        $targetUrl .= 'order' . DS . 'orders';
        $targetUrl .= ($dataJson) ? DS . $dataJson : '';
        $this->_initCh($ch, 'GET');

        return $this->_getHeaders($apiKey);
    }

    /**
     * set curl opt, construct final $targetUrl and build Headers for PUT order
     *
     * @param Resource $ch
     * @param string $targetUrl
     * @param string $apiKey
     * @param string $dataJson
     *
     * @return array
     */
    protected function _putorder(&$ch, &$targetUrl, $apiKey, $dataJson)
    {
        $this->_initCh($ch, 'PUT', $dataJson);

        return $this->_getHeaders($apiKey, $dataJson);
    }

    /**
     * set curl opt, construct final $targetUrl and build Headers for POST payment
     *
     * @deprecated
     *
     * @param Resource $ch
     * @param string $targetUrl
     * @param string $apiKey
     * @param string $dataJson
     *
     * @return array
     */
    protected function _payment(&$ch, &$targetUrl, $apiKey, $dataJson)
    {
        $targetUrl .= 'payment' . DS . 'payments';
        $this->_initCh($ch, 'POST', $dataJson);

        return $this->_getHeaders($apiKey, $dataJson);
    }

    /**
     * Init 2 curl opt according to type
     *
     * @param Resource $ch
     * @param string $type
     * @param string $dataJson
     */
    protected function _initCh(&$ch, $type, $dataJson = false)
    {
        // @codingStandardsIgnoreLine
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        if ($dataJson) {
            // @codingStandardsIgnoreLine
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
        }
    }

    /**
     * Return Headers for curl request
     *
     * @param string $apiKey
     * @param string $dataJson
     *
     * @return array
     */
    protected function _getHeaders($apiKey, $dataJson = false)
    {
        $headers = array(
            'Authorization: Bearer ' . $apiKey . '',
            'Content-Type: application/json',
        );

        if ($dataJson) {
            $headers[] = 'Content-Length: ' . strlen($dataJson);
        }

        return $headers;
    }

    /**
     * Get config from Magento
     *
     * @param string $code
     *
     * @return mixed
     */
    protected function _getConfig($code)
    {
        return Mage::getStoreConfig("payment/oyst_abstract/$code");
    }

    /**
     * Magento user agent
     *
     * @return string
     */
    public function getUserAgent()
    {
        /** @var Oyst_Oyst_Helper_Data $oystHelper */
        $oystHelper = Mage::helper('oyst_oyst');

        return sprintf(
            'Magento v%s - %s v%s',
            Mage::getVersion(),
            Oyst_Oyst_Model_Payment_Method_Freepay::PAYMENT_METHOD_NAME,
            $oystHelper->getExtensionVersion()
        );
    }

    /**
     * API call to Oyst Payment
     *
     * @param string $type
     * @param string $paymentId
     * @param int $amount
     *
     * @return OystPaymentApi
     *
     * @internal param array $dataFormated
     */
    public function sendCancelOrRefund($type, $paymentId, $amount = null)
    {
        $apiKey = $this->_getConfig('api_login');
        $userAgent = $this->getUserAgent();
        $env = $this->_getConfig('mode');
        $url = $this->getCustomApiUrl();

        /** @var OystPaymentApi $oystClient */
        $oystClient = OystApiClientFactory::getClient($type, $apiKey, $userAgent, $env, $url);

        if (!is_null($amount)) {
            $amount = new OystPrice($amount, self::CURRENCY);
        }

        $oystClient->cancelOrRefund($paymentId, $amount);

        if (200 !== $oystClient->getLastHttpCode()) {
            /** @var Oyst_Oyst_Helper_Data $oystHelper */
            $oystHelper = Mage::helper('oyst_oyst');

            $oystHelper->log($oystClient->getLastHttpCode());
            $oystHelper->log($oystClient->getLastError());
            $oystHelper->log($oystClient->getNotifyUrl());
            $oystHelper->log($oystClient->getBody());
            $oystHelper->log($oystClient->getResponse());

            Mage::throwException($oystHelper->__('Bad FreePay API HttpCode. Check oyst.log.'));
        }

        return $oystClient;
    }

    /**
     * Get custom api url from config
     *
     * @return string|null
     */
    public function getCustomApiUrl()
    {
        if (Oyst_Oyst_Model_Source_Mode::CUSTOM === $this->_getConfig('mode')) {
            return $this->_getConfig('api_url');
        }

        return null;
    }
}
