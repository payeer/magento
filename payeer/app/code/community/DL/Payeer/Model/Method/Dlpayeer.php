<?php

class DL_Payeer_Model_Method_Dlpayeer extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'dlpayeer';
    protected $_formBlockType = 'dlpayeer/single_form';
    protected $_infoBlockType = 'dlpayeer/single_info';
    protected $_isGateway = true;
    protected $_canOrder = false;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseRobonal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = false;
    protected $_canCreateBillingAgreement = false;
    protected $_canManageRecurringProfiles = false;
    protected $_canEdit = false;
    protected $_canUseInternal = false; 
    protected $_isActive = 0;
    protected $_title;
    protected $_description;
    protected $_isLogenabled = 0;
	protected $_sMerchantURL;
    protected $_sMerchantID;
    protected $_sInvDesc;
    protected $_paymentText;
    protected $_transferCurrency;
    protected $_sMerchantSecret;
    protected $_sMerchantIPFilter;
	protected $_AEmail;
    protected $_configRead = false;
	
    public function __construct()
    {
        parent::__construct();
        $this->readConfig();
    }

    protected function readConfig()
    {
        if ($this->_configRead) 
		{
            return;
        }
		
        $this->_isActive = $this->getConfigData('active');
		
        $this->_title = $this->getConfigData('title');

        $this->_paymentText = $this->getConfigData('payment_text');
		
        $this->_description = Mage::helper('cms')->getBlockTemplateProcessor()->filter($this->_paymentText);

		$this->_sMerchantURL = $this->getConfigDataRobo('sMerchantURL');
		
        $this->_sMerchantID = $this->getConfigDataRobo('sMerchantID');

        $this->_sInvDesc = $this->getConfigDataRobo('sInvDesc');
		
        $this->_transferCurrency = Mage::app()->getBaseCurrencyCode();

        $this->_sMerchantSecret = Mage::helper('core')->decrypt($this->getConfigDataRobo('sMerchantSecret'));
		
        $this->_sMerchantIPFilter = Mage::helper('core')->decrypt($this->getConfigDataRobo('IPFilter'));
		
		$this->_AEmail = Mage::helper('core')->decrypt($this->getConfigDataRobo('sAdminEmail'));

        $this->_configRead = true;

        return;
    }

    public function getConfigDataRobo($field, $storeId = null)
    {
        if (null === $storeId) 
		{
            $storeId = $this->getStore();
        }
        $path = 'payment/dlpayeer/' . $field;
        return Mage::getStoreConfig($path, $storeId);
    }

    public function getDescription()
    {
        $this->readConfig();
        return $this->_description;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return $this->getRedirectUrl();
    }

    public function getRedirectUrl()
    {
        return Mage::getUrl('dlpayeer/redirect');
    }

    public function isAvailable($quote = null)
    {
        return parent::isAvailable($quote);
    }

    public function canUseForCurrency($currencyCode)
    {
        $baseCurrency = Mage::app()->getWebsite()->getBaseCurrency();
        $rate = $baseCurrency->getRate($this->_transferCurrency);
        $displayCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $reverseRate = $baseCurrency->getRate($displayCurrencyCode);
        if (!$rate || !$reverseRate) 
		{
            Mage::helper("dlpayeer")->log('There is no rate for [' . $displayCurrencyCode . "/"
                . $this->_transferCurrency
                . '] to convert order amount. Payment method Payeer not displayed.');
            return false;
        }
        return true;
    }

    public function canUseForCountry($country)
    {
        if (Mage::getStoreConfig('payment/dlpayeer/allowspecific') == 1) 
		{
            $availableCountries = explode(',', Mage::getStoreConfig('payment/dlpayeer/specificcountry'));
            if (!in_array($country, $availableCountries)) 
			{
                return false;
            }
        }
        return true;
    }

    public function preparePaymentData($order)
    {
        $this->readConfig();

        if (empty($this->_sMerchantID) || empty($this->_sMerchantSecret)) 
		{
            Mage::helper("dlpayeer")
                ->log('Please enter login information about your Payeer merchant in admin panel!');
        }

        $outSum = $this->getOutSum($order);
		
		$m_shop = $this->_sMerchantID;
		$m_orderid = $order->getIncrementId();
		$m_amount = number_format($outSum, 2, '.', '');
		$m_curr = $order->getBaseCurrencyCode();
		$m_desc = base64_encode($this->_sInvDesc);
		$m_key = $this->_sMerchantSecret;
		 
		$arHash = array(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));

        $postData = array(
			"m_shop" => $m_shop,
			"m_orderid" => $m_orderid,
			"m_amount" => $m_amount,
			"m_curr" => $m_curr,
			"m_desc" => $m_desc,
			"m_sign" => $sign
        );
		
        $result = array(
            'postData' => new Varien_Object($postData),
            'order' => $order,
        );
		
        $postData = $result['postData']->getData();
		
        return $postData;
    }

    public function getGateUrl()
    {
        $this->readConfig();
        return $this->_sMerchantURL;
    }

    public function getOrderId($answer)
    {
        return isset($answer["InvId"]) ? $answer["InvId"] : "";
    }

    public function getOutSum($order)
    {
        $outSum = $order->getBaseCurrency()->convert($order->getBaseGrandTotal(), $this->_transferCurrency);
        return $outSum;
    }
}