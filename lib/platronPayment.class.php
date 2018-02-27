<?php

/**
 * @property-read string $merchant
 * @property-read string $secret_key
 * @property-read string $lifetime
 * @property-read string $testmode
 * @property-read string $ofd_send_receipt
 * @property-read string $VAT_type
 */
class platronPayment extends waPayment implements waIPayment, waIPaymentCancel, waIPaymentRefund
{
    private $order_id;
    private $url = 'https://www.platron.ru/init_payment.php';
    private $receiptUrl = 'https://www.platron.ru/receipt.php';

    public function allowedCurrency()
    {
        return array(
            'RUB',
            'USD',
            'EUR',
        );
    }
	
	protected function init()
    {
        // Подключение класса, который работает с подписью запросов.
        $autload = waAutoload::getInstance();
        $autload->add("PG_Signature", "wa-plugins/payment/platron/lib/PG_Signature.php");
        $autload->add("OfdReceiptRequest", "wa-plugins/payment/platron/lib/OfdReceiptRequest.php");
        $autload->add("OfdReceiptItem", "wa-plugins/payment/platron/lib/OfdReceiptItem.php");
        return parent::init();
    }

    public function payment($payment_form_data, $order_data, $auto_submit = true)
    {
        $order = waOrder::factory($order_data);
		
        $allowed = (array) $this->allowedCurrency();
        if (!in_array($order_data['currency_id'], $allowed)) {
            return array(
                'type' => 'error',
                'data' => 'not allowed currency '.$order_data['currency_id'],
            );
        }

        $ofdReceiptItems = array();
        foreach($order_data->items as $arrItem) {
            $ofdReceiptItem = new OfdReceiptItem();
            $ofdReceiptItem->label = substr($arrItem['name'], 0, 128);
            $ofdReceiptItem->amount = round($arrItem['price'] * $arrItem['quantity'], 2);
            $ofdReceiptItem->price = str_replace(',', '.', strval(round($arrItem['price'] - ifset($arrItem['discount'], 0.0), 2)));
            $ofdReceiptItem->quantity = $arrItem['quantity'];
            $ofdReceiptItem->vat = $this->VAT_type;
            $ofdReceiptItems[] = $ofdReceiptItem;
        }

        if (floatval($order_data->shipping) > 0) {
			$ofdReceiptItem = new OfdReceiptItem();
			$ofdReceiptItem->label = $order_data->shipping_name ? $order_data->shipping_name : 'Shipping';
			$ofdReceiptItem->amount = round($order_data->shipping, 2);
			$ofdReceiptItem->price = str_replace(',', '.', strval(round($order_data->shipping, 2)));
			$ofdReceiptItem->quantity = 1;
			$ofdReceiptItem->vat = $this->VAT_type == 'none' ? 'none' : '18';
			$ofdReceiptItems[] = $ofdReceiptItem;
        }

        $form_fields = array(
            'pg_merchant_id'	=> $this->merchant,
            'pg_order_id'       => $order_data['order_id'],
    	    'pg_currency'       => $order_data['currency_id'],
            'pg_amount'         => number_format($order_data['total'], 2, '.', ''),
            'pg_lifetime'       => $this->lifetime*60, // в секундах
    	    'pg_testing_mode'   => $this->testmode == ''? 0 : 1,
    	    //'pg_user_ip'        => $_SERVER['REMOTE_ADDR'],
            'pg_description'	=> mb_substr($order_data['description'], 0, 255, "UTF-8"),
//          'pg_check_url'		=> $this->getRelayUrl().'index.php?app_id='.$this->app_id."&wa_merchant_id=".$this->merchant_id."&type=check",
            'pg_result_url'		=> $this->getRelayUrl().'index.php?app_id='.$this->app_id."&wa_merchant_id=".$this->merchant_id."&type=result",
            'pg_success_url'	=> $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, array('order_id' => $order_data['order_id'])),
            'pg_failure_url'	=> $this->getAdapter()->getBackUrl(waAppPayment::URL_DECLINE, array('order_id' => $order_data['order_id'])),
            'pg_salt'			=> rand(21,43433), // Параметры безопасности сообщения. Необходима генерация pg_salt и подписи сообщения.
            'cms_payment_module'=> 'webasyst',
        );
		
		preg_match_all("/\d/", @$order->contact_phone, $arrPhone);
		$strPhone = implode('',$arrPhone[0]);
			if(strlen($strPhone) == 11)
				$form_fields['pg_user_phone'] = '7'.substr($strPhone,1);	
			if(strlen($strPhone) == 10)
				$form_fields['pg_user_phone'] = $strPhone;
			if(strlen($strPhone) == 9)
				$form_fields['pg_user_phone'] = '7'.$strPhone;
			
		if(preg_match('/^.+@.+\..+$/', @$order->contact_email)){
			$form_fields['pg_user_email'] = $order_data['contact_email'];
			$form_fields['pg_user_contact_email'] = $order_data['contact_email'];
		}

		$form_fields['pg_sig'] = PG_Signature::make('init_payment.php', $form_fields, $this->secret_key);

		$response = file_get_contents($this->url . '?' . http_build_query($form_fields));
		$responseElement = new SimpleXMLElement($response);

		$checkResponse = PG_Signature::checkXML('init_payment.php', $responseElement, $this->secret_key);

		$view = wa()->getView();

		$view->assign('form_fields', $form_fields);
		$view->assign('form_url', (string)$responseElement->pg_redirect_url);
		$view->assign('auto_submit', $auto_submit);

		if ($checkResponse && (string)$responseElement->pg_status == 'ok') {

			if ($this->ofd_send_receipt == 1) {

				$paymentId = (string)$responseElement->pg_payment_id;

				$ofdReceiptRequest = new OfdReceiptRequest($this->merchant, $paymentId);
				$ofdReceiptRequest->items = $ofdReceiptItems;
				$ofdReceiptRequest->sign($this->secret_key);

				$responseOfd = file_get_contents($this->receiptUrl . '?' . http_build_query($ofdReceiptRequest->requestArray()));
                $responseElementOfd = new SimpleXMLElement($responseOfd);

                if ((string)$responseElementOfd->pg_status != 'ok') 
                    throw new waException('<h3>Error. Platron OFD check create failed. ' . $responseElementOfd->pg_error_description . '.</h3>');
			}

        } else {
			throw new waException('<h3>Error. Platron init payment failed. Payment aborted. ' . $responseElement->pg_error_description . '</h3>');
		}

        return $view->fetch($this->path.'/templates/payment.html');

    }

    public function callbackInit($request)
    {
		$this->app_id = ifset($request['app_id']);
		$this->merchant_id = ifset($request['wa_merchant_id']);
        $this->order_id = ifset($request['pg_order_id']);
		
	    return parent::callbackInit($request);
    }

    public function callbackHandler($request)
    {	
		$thisScriptName = PG_Signature::getOurScriptName();
		if (empty($request['pg_sig']) || !PG_Signature::check($request['pg_sig'], $thisScriptName, $request, $this->secret_key) )
			throw new waPaymentException('Invalid sign.');

		$transaction_data = $this->formalizeData($request);
		$arrResp = array();
		
		if($request['type'] == 'check'){
			
			// check пока нельзя сделать, т.к. из модуля невозможно узнать статус заказа
			$bCheckResult = 1;
			if(!$bCheckResult)
				$error_desc = "Товар не доступен";
			
			$arrResp['pg_salt']              = $request['pg_salt']; // в ответе необходимо указывать тот же pg_salt, что и в запросе
			$arrResp['pg_status']            = $bCheckResult ? 'ok' : 'error';
			$arrResp['pg_error_description'] = $bCheckResult ?  ""  : $error_desc;
			$arrResp['pg_sig']				 = PG_Signature::make($thisScriptName, $arrResp, $this->secret_key);
			
			$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
			$objResponse->addChild('pg_salt', $arrResp['pg_salt']);
			$objResponse->addChild('pg_status', $arrResp['pg_status']);
			$objResponse->addChild('pg_error_description', $arrResp['pg_error_description']);
			$objResponse->addChild('pg_sig', $arrResp['pg_sig']);
			
			print $objResponse->asXML();
		}
		elseif($request['type'] == 'result'){
			$app_payment_method = null;
			if ($request['pg_result'] == 1) {
				$app_payment_method = self::CALLBACK_PAYMENT;
				$transaction_data['state'] = self::STATE_CAPTURED;
				$transaction_data['type'] = self::OPERATION_AUTH_CAPTURE;
			}
			else {
				$app_payment_method = self::CALLBACK_CANCEL;
				$transaction_data['state'] = self::STATE_CANCELED;
				$transaction_data['type'] = self::OPERATION_CANCEL;
			}
			
			$transaction_data = $this->saveTransaction($transaction_data, $request);
			if ($app_payment_method) {
				$result = $this->execAppCallback($app_payment_method, $transaction_data);
				self::addTransactionData($transaction_data['id'], $result);
			}
			
			$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
			$objResponse->addChild('pg_salt', $request['pg_salt']); // в ответе необходимо указывать тот же pg_salt, что и в запросе
			$objResponse->addChild('pg_status', 'ok'); // !!! Здесь нет возможности проверить ни существования заказа, ни его статус. Так что ответ на оповещение может быть только ОК!
			$objResponse->addChild('pg_description', "Оплата принята");
			$objResponse->addChild('pg_sig', PG_Signature::makeXML($thisScriptName, $objResponse, $this->secret_key));

			header('Content-type: text/xml');
			print $objResponse->asXML();
		}
		else
			throw new waPaymentException('Invalid request type.');
		
		return array('template'=>false);
    }

	 /**
     * @todo
     * (non-PHPdoc)
     * @see waIPaymentRefund::cancel()
     */
    public function cancel($transaction_raw_data)
    {
//	Ждем реализации метода от WebAssyst
    }
    /**
     * @todo
     * (non-PHPdoc)
     * @see waIPaymentRefund::refund()
     */
    public function refund($transaction_raw_data)
    {
//	Ждем реализации метода от WebAssyst
    }
	
    public function getTransactionStatus($transaction_raw_data)
    {
//	Ждем реализаций методов refund и cancel от WebAssyst
    }

    private function getEndpointUrl()
    {
        return $this->url;
    }
	
	protected function formalizeData($transaction_raw_data)
    {
        $transaction_data = parent::formalizeData($transaction_raw_data);
        $transaction_data['view_data'] = "Номер транзакции ".$this->order_id;
		$transaction_data['native_id'] = ifset($transaction_raw_data['pg_payment_id']);
        $transaction_data['order_id'] = ifset($transaction_raw_data['pg_order_id']);
        $transaction_data['amount'] = ifset($transaction_raw_data['pg_amount']);
        $transaction_data['currency_id'] = ifset($transaction_raw_data['pg_currency']);
        //$transaction_data['type'] = ifset($transaction_raw_data['type']);

        return $transaction_data;
    }
}
