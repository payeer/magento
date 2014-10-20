<?php

class DL_Payeer_Block_Adminhtml_System_Config_Form_Field_Linktooptions extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return '<a href="' . $this->getUrl('*/system_config/edit', array('section' => 'payment')) . '">' . Mage::helper('dlpayeer')->__('Go to Payment Methods settings section') . '</a>';
    }
}