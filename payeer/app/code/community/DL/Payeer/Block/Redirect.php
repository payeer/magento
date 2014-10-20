<?php

class DL_Payeer_Block_Redirect extends Mage_Core_Block_Abstract
{
    protected $_postData;

    protected function _toHtml()
    {
        $html = '<html><body>';
        $html .= '<form method="get" action="' . $this->getGateUrl() . '" id="gate_post_form">';
        foreach ($this->_postData as $key => $value) 
		{
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
        }
		
        print '<input type="submit" value="" style="display: none">';
        $html .= '</form><script type="text/javascript">document.getElementById("gate_post_form").submit();</script>';
        $html .= '</body></html>';
        return $html;
    }

    public function setPostData($data)
    {
        $this->_postData = $data;
        return $this;
    }
}