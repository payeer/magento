<?php

if (version_compare(Mage::getVersion(), '1.4.1', '<')) {
    class DL_Payeer_Block_Adminhtml_System_Config_Form_Field_Heading
        extends Mage_Adminhtml_Block_Abstract
            implements Varien_Data_Form_Element_Renderer_Interface
    {

        public function render(Varien_Data_Form_Element_Abstract $element)
        {
            return sprintf(
                '<tr class="system-fieldset-sub-head" id="row_%s"><td colspan="5"><h4 id="%s">%s</h4></td></tr>',
                $element->getHtmlId(), $element->getHtmlId(), $element->getLabel()
            );
        }
    }
} 
else 
{
    class DL_Payeer_Block_Adminhtml_System_Config_Form_Field_Heading extends Mage_Adminhtml_Block_System_Config_Form_Field_Heading
    {
    
	}
}