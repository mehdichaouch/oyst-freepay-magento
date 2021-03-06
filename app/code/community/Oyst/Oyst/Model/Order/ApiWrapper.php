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

/**
 * Order_ApiWrapper Model
 */
class Oyst_Oyst_Model_Order_ApiWrapper extends Mage_Core_Model_Abstract
{
    /**
     * Retrieve order from Oyst
     *
     * @param Int $oystOrderId
     * @return array
     */
    public function getOrder($oystOrderId)
    {
        $response = Mage::getModel('oyst_oyst/api')->send(Oyst_Oyst_Model_Api::TYPE_GETORDER, $oystOrderId);

        return $response;
    }

    /**
     * Update Order Status to Oyst
     *
     * @param Int $oystOrderId
     * @param string $status
     * @return array
     */
    public function putOrder($oystOrderId, $status)
    {
        //if order id => update to Oyst
        if ($oystOrderId && $status) {
            $response = Mage::getModel('oyst_oyst/api')->send(
                Oyst_Oyst_Model_Api::TYPE_PUTORDER,
                array(
                    'oyst_order_id' => $oystOrderId,
                    'status' => $status
                )
            );
        } else {
            /** @var Oyst_Oyst_Helper_Data $oystHelper */
            $oystHelper = Mage::helper('oyst_oyst');
            $response = $oystHelper->__("order id %s or status %s not found", $oystOrderId, $status);
        }

        return $response;
    }
}
