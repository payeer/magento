<?php

class DL_Payeer_Model_Culture extends Mage_Core_Model_Abstract
{
    public function toOptionArray()
    {
        $data = array(
            array('value' => "en", 'label' => Mage::helper('dlpayeer')->__('English')),
            array('value' => "ru", 'label' => Mage::helper('dlpayeer')->__('Russian'))
        );
        return $data;
    }
}
