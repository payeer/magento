<?php

class DL_Payeer_Block_Single_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
		parent::_construct();
		$this->setTemplate('dl_payeer/single/info.phtml');
    }
}