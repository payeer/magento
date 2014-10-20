<?php

class DL_Payeer_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function refillCart($order)
    {
        $cartRefilled = true;

        $cart = Mage::getSingleton('checkout/cart');
        $items = $order->getItemsCollection();
        foreach ($items as $item) {
            try 
			{
                $cart->addOrderItem($item);
            } 
			catch (Mage_Core_Exception $e) 
			{
                $cartRefilled = false;
                if (Mage::getSingleton('checkout/session')->getUseNotice(true)) 
				{
                    Mage::getSingleton('checkout/session')->addNotice($e->getMessage());
                } 
				else 
				{
                    Mage::getSingleton('checkout/session')->addError($e->getMessage());
                }
				
                $this->_redirect('customer/account/history');
            } 
			catch (Exception $e) 
			{
                $cartRefilled = false;
                Mage::getSingleton('checkout/session')->addException(
                    $e,
                    Mage::helper('checkout')->__('Cannot add the item to shopping cart.')
                );
            }
        }
        $cart->save();

        return $cartRefilled;
    }

    public function arrayToRawData($array)
    {
        foreach ($array as $key => $value) {
            $newArray[] = $key . ": " . $value;
        }
        $raw = implode("\r\n", $newArray);
        return $raw;
    }

    public function isLogEnabled()
    {
        return Mage::getStoreConfig('payment/dlpayeer/enable_log');
    }

    public function getLogFileName()
    {
        return 'dl_payeer.log';
    }
}
