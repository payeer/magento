<?php

class DL_Payeer_Block_Adminhtml_System_Config_Form_Field_Linktoinfo extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return '<a href="' . $this->getUrl('*/system_config/edit', array('section' => 'dlpayeer')) . '">' . Mage::helper('dlpayeer')->__('Extension information') . '</a>';
    }
}