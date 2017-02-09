<?php

/**
 * Created by PhpStorm.
 * User: Boxedsolutions
 * Date: 2016-09-26
 * Time: 11:08
 */
class Billmate_Common_Block_Checkout_Cart extends Mage_Checkout_Block_Cart
{
    public function getCheckoutUrl()
    {
        if (Mage::getStoreConfig('billmate/checkout/active') == 1) {
            return $this->getUrl('billmatecommon/billmatecheckout', array('_secure'=>true));
        }else{
            return parent::getCheckoutUrl();
        }
    }
}