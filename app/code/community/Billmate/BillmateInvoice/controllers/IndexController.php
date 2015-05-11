<?php


class Billmate_BillmateInvoice_IndexController extends Mage_Core_Controller_Front_Action{

    function IndexAction(){
        $quote =  Mage::getSingleton('checkout/session')->getQuote();
                
        if ($this->getRequest()->isPost()){

			$payment = $_POST['payment'];
            $pophide = $this->getRequest()->getPost('pophide',false);
            if($pophide === false) {
                if (!in_array($payment['method'], array('billmateinvoice', 'partpayment'))) {
                    echo 'payment.saveUrl=oldurl;payment.save();payment.onComplete=function(){checkout.setLoadWaiting(false);payment.saveUrl = billmateindexurl;payment.onComplete = function(res){ checkout.setLoadWaiting(false); eval(res.responseText);}}';
                    return;
                }
                if(Mage::getStoreConfig('firecheckout/general/enabled')) {
                    if (empty($payment['person_number']) && empty($payment[$payment['method'].'_pno'])) {
						die('alert("' . Mage::helper('payment')->__('Missing Personal number') . '")');
                    }
                }
                else {
                    if (empty($payment[$payment['method'] . '_pno'])) {
                        die('alert("' . Mage::helper('payment')->__('Missing Personal number') . '")');
                    }
                }
                if (empty($payment[$payment['method'] . '_phone'])) {
                    die('alert("' . Mage::helper('payment')->__('Please confirm email address is accurate and can be used for billing') . '")');
                }
            }
		    
			$gateway = Mage::getSingleton('billmateinvoice/gateway');
            $gateway->init();
            
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->renderLayout();
        }
    
    }
	function getInfoAction(){
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
	}
    function updateAddressAction(){
        if ($this->getRequest()->isPost()){
            $gateway = Mage::getSingleton('billmateinvoice/gateway');
            $gateway->init(true);
            
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->renderLayout();
        }
    }
}
