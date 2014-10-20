<?php

class DL_Payeer_Block_Single_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('dl_payeer/single/form.phtml');
    }
}