<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011 Crescendo Multimedia Ltd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Merchant eWAY Class
 *
 * Payment processing using eWAY
 */

class Merchant_eway extends CI_Driver {

	public $name = 'eWAY';

	const PROCESS_URL = 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp';
	const PROCESS_URL_TEST =  'https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp';

	public $required_fields = array('amount', 'card_no', 'card_name', 'exp_month', 'exp_year', 'csc', 'currency_code', 'transaction_id', 'reference');

	public $settings = array(
		'eWAYCustomerID' => '',
		'test_mode' => FALSE
	);

	public function _process($params)
	{
		if ($params['currency_code'] !== 'AUD')	// check currency code is AUD
		{
			return new Merchant_response('failed', 'Required field "currency_code" was not in AUD.');
		}

		if ($this->settings['test_mode'])
		{
			if ($params['card_no'] !== '4444333322221111')
			{
				return new Merchant_response('failed', 'Required field "card_no" was incorrect for test mode.');
			}
			elseif ($this->settings['eWAYCustomerID'] !== '87654321')
			{
				return new Merchant_response('failed', 'Required field "eWAYCustomerID" was incorrect for test mode.');
			}
		}

		$params['exp_year'] = $params['exp_year'] % 100;
		$params['amount'] = $params['amount'] * 100;

		$xmlRequest = '<ewaygateway>'.
	      		'<ewayCustomerID>'.$this->settings['eWAYCustomerID'].'</ewayCustomerID>'.
	      		'<ewayTotalAmount>'.$params['amount'].'</ewayTotalAmount>'.
	      		'<ewayCustomerInvoiceDescription>'.$params['reference'].'</ewayCustomerInvoiceDescription>'.
	      		'<ewayCustomerInvoiceRef>'.$params['transaction_id'].'</ewayCustomerInvoiceRef>'.
	      		'<ewayCardHoldersName>'.$params['card_name'].'</ewayCardHoldersName>'.
	      		'<ewayCardNumber>'.$params['card_no'].'</ewayCardNumber>'.
	      		'<ewayCardExpiryMonth>'.$params['exp_month'].'</ewayCardExpiryMonth>'.
	      		'<ewayCardExpiryYear>'.$params['exp_year'].'</ewayCardExpiryYear>'.
	      		'<ewayTrxnNumber>'.$params['transaction_id'].'</ewayTrxnNumber>'.
	      		'<ewayCVN>'.$params['csc'].'</ewayCVN>'.
				'<ewayCustomerFirstName></ewayCustomerFirstName>'.
				'<ewayCustomerLastName></ewayCustomerLastName>'.
				'<ewayCustomerEmail></ewayCustomerEmail>'.
				'<ewayCustomerAddress></ewayCustomerAddress>'.
				'<ewayCustomerPostcode></ewayCustomerPostcode>'.
				'<ewayOption1></ewayOption1>'.
				'<ewayOption2></ewayOption2>'.
				'<ewayOption3></ewayOption3>'.
			'</ewaygateway>';

		$curl = curl_init($this->settings['test_mode'] ? self::PROCESS_URL_TEST : self::PROCESS_URL);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $xmlRequest);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($curl);
		curl_close($curl);
		$xml = simplexml_load_string($response);

		if ( ! isset($xml->ewayTrxnStatus))
		{
			return new Merchant_response('failed', 'invalid_response - '.$xml);
		}
		elseif ($xml->ewayTrxnStatus == 'True')
		{
			return new Merchant_response('authorized', 'AuthCode - '.(string)$xml->ewayAuthCode.', ErrorCode - '.(string)$xml->ewayTrxnError, (string)$xml->ewayTrxnReference, (string)$xml->ewayReturnAmount);
		}
		else
		{
			return new Merchant_response('declined', 'ErrorCode - '.(string)$xml->ewayTrxnError, (string)$xml->ewayTrxnReference);
		}
	}
}

/* End of file ./libraries/merchant/drivers/merchant_eway.php */