<?php
class Billmate_Cardpay_Model_BillmateCardpay extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'billmatecardpay';
    protected $_formBlockType = 'billmatecardpay/form';
    
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_isInitializeNeeded      = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_liveurl                 = 'https://cardpay.billmate.se/pay';
    protected $_testurl                 = 'https://cardpay.billmate.se/pay/test';

    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }

    public function isAvailable($quote = null)
    {
        if($quote == null ) return false;
		if( Mage::getStoreConfig('payment/billmatecardpay/active') != 1 ) return false;
        $countries = explode(',', Mage::getStoreConfig('payment/billmatecardpay/countries'));	
        if( in_array($quote->getShippingAddress()->getCountry(), $countries ) ){
			//$data = $quote->getTotals();
            $total = $quote->getSubtotal();
			$min_total = Mage::getStoreConfig('payment/billmatecardpay/min_amount');
			$max_total = Mage::getStoreConfig('payment/billmatecardpay/max_amount');
			return $total >= $min_total && $total <= $max_total;
		}
		return false;
    }

	public function cancel( Varien_Object $payment )
	{

		$this->void($payment);
		return $this;
	}

	public function void( Varien_Object $payment )
	{
		$k = Mage::helper('billmateinvoice')->getBillmate(true,false);
		$invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
		$values = array(
			'number' => $invoiceId
		);
		$paymentInfo = $k->getPaymentInfo($values);
		if($paymentInfo['PaymentData']['status'] == 'Created'){
			$result = $k->cancelPayment($values);
			if(isset($result['code'])){
				Mage::throwException($result['message']);
			}
			$payment->setTransactionId($result['number']);
			$payment->setIsTransactionClosed(1);
		}

		return $this;
	}


    public function capture(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation')) {
            $k = Mage::helper('billmatecardpay')->getBillmate(true, false);
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $values = array(
                'number' => $invoiceId
            );
            $paymentInfo = $k->getPaymentInfo($values);
            if (is_array($paymentInfo) && $paymentInfo['PaymentData']['status'] == 'Created') {
                $boTotal = $paymentInfo['Cart']['Total']['withtax']/100;
                if($amount != $boTotal){
                    Mage::throwException(Mage::helper('billmatecommon')->__('The amounts don\'t match. Billmate Online %s and Store %s. Activate manually in Billmate.',$boTotal,$amount));
                }
                $result = $k->activatePayment(array('PaymentData' => $values));
                if(isset($result['code']) )
                    Mage::throwException(utf8_encode($result['message']));
                if(!isset($result['code'])){
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                }

            }
        }
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if(Mage::getStoreConfig('billmate/settings/activation') && Mage::getStoreConfig('payment/billmatecardpay/payment_action') == 'authorize') {
            $k = Mage::helper('billmatecardpay')->getBillmate(true, false);
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $values = array(
                'number' => $invoiceId
            );
            $paymentInfo = $k->getPaymentInfo($values);
            if ($paymentInfo['PaymentData']['status'] == 'Paid') {
                $values['partcredit'] = false;
                $result = $k->creditPayment(array('PaymentData' => $values));
                if(isset($result['code']) )
                    Mage::throwException(utf8_encode($result['message']));
                if(!isset($result['code'])){
                    $payment->setTransactionId($result['number']);
                    $payment->setIsTransactionClosed(1);
                }
                Mage::log('result' . print_r($result, true));
            }
            Mage::log(print_r($paymentInfo,true));
        } else {
            $invoiceId = $payment->getMethodInstance()->getInfoInstance()->getAdditionalInformation('invoiceid');
            $payment->setTransactionId($invoiceId);
            $payment->setIsTransactionClosed(1);
        }
        return $this;
    }

    public function getConfig()
    {
        if (null === $this->_config) {
            $params = array($this->_code);
            if ($store = $this->getStore()) {
                $params[] = is_object($store) ? $store->getId() : $store;
            }
            $this->_config = Mage::getModel('billmatecardpay/config', $params);
        }
        return $this->_config;
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    
    public function getStandardCheckoutFormFields(){

		$session = Mage::getSingleton("core/session",  array("name"=>"frontend"));
        $session->unsetData('card_api_called');
        
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        /* @var $api Billmate_Cardpay_Model_Api_Standard */

		$prompt_name = Mage::getStoreConfig('payment/billmatecardpay/prompt_name') == 1 ? 'YES' : 'NO';
		$do3dsecure = Mage::getStoreConfig('payment/billmatecardpay/do_3d_secure') == 0 ? 'NO' : 'YES';

        $api = Mage::getModel('billmatecardpay/api_standard')->setConfigObject($this->getConfig());
        $api->setOrderId($orderIncrementId)
            ->setCurrencyCode($order->getOrderCurrencyCode())
            //->setPaymentAction()
            ->setOrder($order)
			->setReturnMethod('GET')
			->setPromptNameEntry($prompt_name)
			->setDo3dSecure($do3dsecure)
            ->setNotifyUrl('http://api.billmate.se/callback.php')
            ->setReturnUrl(Mage::getUrl('cardpay/cardpay/success'))
            ->setCancelUrl(Mage::getUrl('cardpay/cardpay/cancel'));
            
        // add cart totals and line items
        $api->setBillmateCart(Mage::getModel('paypal/cart', array($order)))
            ->setIsLineItemsEnabled($this->_config->lineItemsEnabled);
        
        $result = $api->getStandardCheckoutRequest();

        return $result;        
    }
    
   
    public function getTitle(){
        return (strlen(Mage::getStoreConfig('payment/billmatecardpay/title')) > 0) ? Mage::helper('billmatecardpay')->__(Mage::getStoreConfig('payment/billmatecardpay/title')) : Mage::helper('billmatecardpay')->__('Billmate Cardpay');
    }
    public function getBillmateUrl(){
        
        if( Mage::getStoreConfig('payment/billmatecardpay/test_mode') == '1'){
            return $this->_testurl;
        } else {
            return $this->_liveurl;
        }
    }
    public function getOrderPlaceRedirectUrl()
    {
        //when you click on place order you will be redirected on this url, if you don't want this action remove this method
        $session = Mage::getSingleton('checkout/session');
        $session->setBillmateQuoteId($session->getQuoteId());
        $gateway = Mage::getSingleton('billmatecardpay/gateway');

        $result = $gateway->makePayment();


        return $result['url'];
    }

	/*
    public function validate(){
    }*/
}
?>