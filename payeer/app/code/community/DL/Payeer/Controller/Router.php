<?php

class DL_Payeer_Controller_Router extends Mage_Core_Controller_Varien_Router_Abstract
{
    protected $_routerController;
    protected $_routerName = 'dlpayeer';
    protected $_moduleName = 'dlpayeer';
    public function initControllerRouters($observer)
    {
        $front = $observer->getEvent()->getFront();
        $this->setRouterController();
        $front->addRouter($this->_routerName, $this->_routerController);
    }

    public function setRouterController()
    {
        $this->_routerController = new DL_Payeer_Controller_Router();
    }

    public function match(Zend_Controller_Request_Http $request)
    {
        if (!Mage::isInstalled()) 
		{
            Mage::app()->getFrontController()->getResponse()
                ->setRedirect(Mage::getUrl('install'))
                ->sendResponse();
            exit;
        }

        $identifier = trim($request->getPathInfo(), '/');
        $allpath = explode("/", $identifier);

        if ($this->_isOurModule($allpath[0])) 
		{
            return false;
        }

        $allpath[2] = $allpath[1];
        $allpath[1] = 'gate';

        if ($orderId = $request->getParam("InvId")) 
		{
            Mage::app()->getStore()->load(Mage::getModel("sales/order")->load($orderId)->getStoreId());
        }


        $request->setModuleName($this->_moduleName)
            ->setControllerName(isset($allpath[1]) ? $allpath[1] : 'gate')
            ->setActionName(isset($allpath[2]) ? $allpath[2] : 'success');
        $request->setAlias(
            Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
            trim($request->getPathInfo(), '/')
        );

        return true;
    }


    protected function _isOurModule($urlKey)
    {
        return ($urlKey != $this->_routerName);
    }
}