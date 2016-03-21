<?php

class DL_Payeer_GateController extends Mage_Core_Controller_Front_Action
{
    const MODULENAME = "dlpayeer";
    const PAYMENTNAME = "dlpayeer";

    protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) 
		{
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            exit;
        }
    }

    public function redirectAction()
    {
        $helper = Mage::helper("dlpayeer");
        $session = Mage::getSingleton('checkout/session');
        $state = Mage_Sales_Model_Order::STATE_NEW;
        $status = 'pending';

        $order = Mage::getModel('sales/order')->load($session->getLastOrderId());
        $order->setState(
			$state,
            $status,
            $this->__('Customer redirected to payment Gateway Payeer'),
            false
		);
			
        $order->save();

        $payment = $order->getPayment()->getMethodInstance();
		
        if (!$payment) 
		{
            $payment = Mage::getSingleton("dlpayeer/method_dlpayeer");
        }

        $dataForSending = $payment->preparePaymentData($order);
        $this->getResponse()->setHeader('Content-type', 'text/html; charset=UTF8');
        $this->getResponse()->setBody(
			$this->getLayout()->createBlock('dlpayeer/redirect')->setGateUrl(
			$payment->getGateUrl())->setPostData($dataForSending)->toHtml()
        );
    }

    public function statusAction()
    {
		$request = $this->getRequest()->getParams();
        $helper = Mage::helper("dlpayeer");
		
        if (isset($request["m_operation_id"]) && isset($request["m_sign"]))
		{
			$err = false;
			$message = '';
			
			// запись логов
			
			$log_text = 
			"--------------------------------------------------------\n" .
			"operation id		" . $request['m_operation_id'] . "\n" .
			"operation ps		" . $request['m_operation_ps'] . "\n" .
			"operation date		" . $request['m_operation_date'] . "\n" .
			"operation pay date	" . $request['m_operation_pay_date'] . "\n" .
			"shop				" . $request['m_shop'] . "\n" .
			"order id			" . $request['m_orderid'] . "\n" .
			"amount				" . $request['m_amount'] . "\n" .
			"currency			" . $request['m_curr'] . "\n" .
			"description		" . base64_decode($request['m_desc']) . "\n" .
			"status				" . $request['m_status'] . "\n" .
			"sign				" . $request['m_sign'] . "\n\n";
			
			$log_file = Mage::getStoreConfig('payment/dlpayeer/enable_log');
			
			if (!empty($log_file))
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
			}
			
			// проверка цифровой подписи и ip

			$sign_hash = strtoupper(hash('sha256', implode(":", array(
				$request['m_operation_id'],
				$request['m_operation_ps'],
				$request['m_operation_date'],
				$request['m_operation_pay_date'],
				$request['m_shop'],
				$request['m_orderid'],
				$request['m_amount'],
				$request['m_curr'],
				$request['m_desc'],
				$request['m_status'],
				Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/dlpayeer/sMerchantSecret'))
			))));
			
			$valid_ip = true;
			$sIP = str_replace(' ', '', Mage::getStoreConfig('payment/dlpayeer/IPFilter'));
			
			if (!empty($sIP))
			{
				$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
				if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
				'(' . $arrIP[1] . '|\*{1})(\.)' .
				'(' . $arrIP[2] . '|\*{1})(\.)' .
				'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
				{
					$valid_ip = false;
				}
			}
			
			if (!$valid_ip)
			{
				$message .= $helper->__(" - the ip address of the server is not trusted") . "\n" .
				$helper->__("   trusted ip: ") . $sIP . "\n" .
				$helper->__("   ip of the current server: ") . $_SERVER['REMOTE_ADDR'] . "\n";
				$err = true;
			}

			if ($request['m_sign'] != $sign_hash)
			{
				$message .= $helper->__(" - Do not match the digital signature") . "\n";
				$err = true;
			}
			
			if (!$err)
			{
				// загрузка заказа
				
				$order = Mage::getModel('sales/order')->loadByIncrementId($request['m_orderid']);
				
				// проверка статуса
				
				$state = Mage_Sales_Model_Order::STATE_PROCESSING;
				$paidStatus = 'complete';
				
				switch ($request['m_status'])
				{
					case 'success':
						if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) 
						{
							if ($order->canInvoice()) 
							{
								$invoice = $order->prepareInvoice();
								$invoice->register()->capture();
								$order->addRelatedObject($invoice);
							}
							
							$result = $this->_sendEmailAfterPaymentSuccess($order);
							
							$order->setState($state,
								$paidStatus,
								$this->__($helper->__('The amount has been authorized and captured by Payeer.')),
								$result
							);
							
							$order->save();
						}
						break;
						
					default:
						if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) 
						{
							$order->addStatusToHistory(
								$order->getStatus(),
								$helper->__('Payment failed'),
								false
							);
							
							$order->save();
							$order->cancel()->save();
						}
						$message .= $helper->__(" - The payment status is not success") . "\n";
						$err = true;
						break;
				}
			}
			
			if ($err)
			{
				$to = Mage::getStoreConfig('payment/dlpayeer/sAdminEmail');

				if (!empty($to))
				{
					$message = $helper->__("Failed to make the payment through the system Payeer for the following reasons:") . "\n\n" . $message . "\n" . $log_text;
					$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
					"Content-type: text/plain; charset=utf-8 \r\n";
					mail($to, $helper->__("Payment error"), $message, $headers);
				}
				
				$this->getResponse()->setBody($request['m_orderid'] . '|error');
			}
			else
			{
				$this->getResponse()->setBody($request['m_orderid'] . '|success');
			}
        }
		else 
		{
			$this->getResponse()->setBody('The operation not found');
        }
    }

    public function failureAction()
    {
		$request = $this->getRequest()->getParams();
        $helper = Mage::helper("dlpayeer");
		
        if (isset($request["m_orderid"]) && isset($request["m_amount"])) 
		{
			$order = Mage::getModel('sales/order')->loadByIncrementId($request['m_orderid']);
            $helper->refillCart($order);
			
            $order->addStatusToHistory(
                $order->getStatus(),
                $helper->__('Payment failed'),
                false
            );
			
            $order->save();
            $order->cancel()->save();
            $this->_redirect('checkout/onepage/failure');
        } 
		else 
		{
            $this->getResponse()->setBody('The operation not found');
        }
    }

    public function successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        if (!$session->getLastOrderId() || !$session->getLastQuoteId() || !$session->getLastSuccessQuoteId()) 
		{
            $answer = $this->getRequest()->getParams();
        }
		
        $this->_redirect("checkout/onepage/success");
    }

    protected function _sendEmailAfterPaymentSuccess($order)
    {
        if ($order->sendNewOrderEmail()) 
		{
            $result = true;
            $order->setEmailSent(true);
        } 
		else 
		{
            $result = false;
        }
		
        return $result;
    }
}