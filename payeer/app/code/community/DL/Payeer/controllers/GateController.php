<?php

class DL_Payeer_GateController extends Mage_Core_Controller_Front_Action
{
    const MODULENAME = "dlpayeer";
    const PAYMENTNAME = "dlpayeer";

    protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
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
        $order->setState($state,
            $status,
            $this->__('Customer redirected to payment Gateway Payeer'),
            false);
        $order->save();

        $payment = $order->getPayment()->getMethodInstance();
        if (!$payment) {
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
        $helper = Mage::helper("dlpayeer");
        $state = Mage_Sales_Model_Order::STATE_PROCESSING;
        $paidStatus = 'complete';
        $errorStatus = 'closed';
		
        if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
		{
			$m_key = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/dlpayeer/sMerchantSecret'));
			$arHash = array($_POST['m_operation_id'],
					$_POST['m_operation_ps'],
					$_POST['m_operation_date'],
					$_POST['m_operation_pay_date'],
					$_POST['m_shop'],
					$_POST['m_orderid'],
					$_POST['m_amount'],
					$_POST['m_curr'],
					$_POST['m_desc'],
					$_POST['m_status'],
					$m_key);
			$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
			
			
			// проверка принадлежности ip списку доверенных ip
			$list_ip_str = str_replace(' ', '', Mage::getStoreConfig('payment/dlpayeer/IPFilter'));
			
			if ($list_ip_str != '') 
			{
				$list_ip = explode(',', $list_ip_str);
				$this_ip = $_SERVER['REMOTE_ADDR'];
				$this_ip_field = explode('.', $this_ip);
				$list_ip_field = array();
				$i = 0;
				$valid_ip = FALSE;
				foreach ($list_ip as $ip)
				{
					$ip_field[$i] = explode('.', $ip);
					if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
						(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
						(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
						(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
						{
							$valid_ip = TRUE;
							break;
						}
					$i++;
				}
			}
			else
			{
				$valid_ip = TRUE;
			}
			
			// запись в логи если требуется
			$log_text = 
			"--------------------------------------------------------\n".
			"operation id		".$_POST["m_operation_id"]."\n".
			"operation ps		".$_POST["m_operation_ps"]."\n".
			"operation date		".$_POST["m_operation_date"]."\n".
			"operation pay date	".$_POST["m_operation_pay_date"]."\n".
			"shop				".$_POST["m_shop"]."\n".
			"order id			".$_POST["m_orderid"]."\n".
			"amount				".$_POST["m_amount"]."\n".
			"currency			".$_POST["m_curr"]."\n".
			"description		".base64_decode($_POST["m_desc"])."\n".
			"status				".$_POST["m_status"]."\n".
			"sign				".$_POST["m_sign"]."\n\n";
			
			if (Mage::getStoreConfig('payment/dlpayeer/enable_log') == 1)
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'].'/var/log/dl_payeer.log', $log_text, FILE_APPEND);
			}
				
			// проверка цифровой подписи и ip сервера
			if ($_POST["m_sign"] == $sign_hash && $_POST['m_status'] == "success" && $valid_ip)
			{
				$order = Mage::getModel('sales/order')->loadByIncrementId($_POST['m_orderid']);
                if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) 
				{
                    if ($order->canInvoice()) {
                        $invoice = $order->prepareInvoice();
                        $invoice->register()->capture();
                        $order->addRelatedObject($invoice);
                    }
                    $result = $this->_sendEmailAfterPaymentSuccess($order);
					$order->setState($state,
					$paidStatus,
					$this->__($helper->__('The amount has been authorized and captured by Payeer.')),
					$result);
					$order->save();
                }
                echo $_POST['m_orderid']."|success";
			}
			else 
			{
				$to = Mage::getStoreConfig('payment/dlpayeer/sAdminEmail');
				$subject = "Payment error";
				$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
				
				if ($_POST["m_sign"] != $sign_hash)
				{
					$message.=" - Do not match the digital signature\n";
				}
				
				if ($_POST['m_status'] != "success")
				{
					$message.=" - The payment status is not success\n";
				}
				
				if (!$valid_ip)
				{
					$message.=" - the ip address of the server is not trusted\n";
					$message.="   trusted ip: ".Mage::getStoreConfig('payment/dlpayeer/IPFilter')."\n";
					$message.="   ip of the current server: ".$_SERVER['REMOTE_ADDR']."\n";
				}
				
				$message .= "\n".$log_text;
				
				$headers = "From: no-reply@".$_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
				mail($to, $subject, $message, $headers);
				
				echo $_POST['m_orderid']."|error";
            }
        } 
		else 
		{
            echo 'The operation not found.';
        }
    }

    public function failureAction()
    {
        $helper = Mage::helper("dlpayeer");
        if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"])) 
		{
			$order = Mage::getModel('sales/order')->loadByIncrementId($_POST['m_orderid']);
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
            echo 'The operation not found';
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
        if ($order->sendNewOrderEmail()) {
            $result = true;
            $order->setEmailSent(true);
        } else {
            $result = false;
        }
        return $result;
    }
}