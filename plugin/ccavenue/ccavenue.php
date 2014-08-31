<?php
/**
 * @package     RedSHOP
 * @subpackage  ccavenue-Plugin
 *
 * @copyright   Copyright (C) 2005 - 2013 Aryavarta Software. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgRedshop_paymentccavenue extends JPlugin
{
	public function onPrePayment($element, $data)
	{
		if ($element != 'ccavenue')
		{
			return;
		}

		if (empty($plugin))
		{
			$plugin = $element;
		}

		$user             = JFactory::getUser();
		$db               = JFactory::getDbo();

		$url          = "https://www.ccavenue.com/shopzone/cc_details.jsp";
		$Merchant_Id  = $this->params->get('Merchant_Id', '');
		$order        = $data['order'];
		$Redirect_Url = JURI::root() . "index.php?tmpl=component&option=com_redshop&view=order_detail&controller=order_detail&task=notify_payment&payment_plugin=ccavenue&orderid=" . $data['order_id'];

		// Generate Checksum
		$securityToken = $this->params->get('security_token', '');
		$Checksum      = $this->generateCheckSum($Merchant_Id, $order->order_subtotal, $order->order_number, $Redirect_Url, $securityToken);

		// Billing Infromation and Addresses
		$billingInfo  = $data['billinginfo'];
		$firstName    = $billingInfo->firstname;
		$lastName     = $billingInfo->lastname;
		$name         = $firstName . " " . $lastName;
		$address      = $billingInfo->address;
		$country      = $billingInfo->country_code;
		$state_2_code = $billingInfo->state_2_code;
		$city         = $billingInfo->city;
		$zipcode      = $billingInfo->zipcode;
		$phone        = $billingInfo->phone;
		$email        = $billingInfo->user_email;

		// Get State 3 Code
		$query = $db->getQuery(true)
				->select('state_3_code')
				->from('#__redshop_state')
				->where('state_2_code = "' . $state_2_code . '"');

		// Inject the query and load the result.
		$db->setQuery($query);
		$state = $db->loadResult();

		// Check for a database error.
		if ($db->getErrorNum())
		{
			JError::raiseWarning(500, $db->getErrorMsg());

			return null;
		}

		// Shipping Information
		$shippingInfo = $data['billinginfo'];
		$shippingName = $shippingInfo->firstname . " " . $shippingInfo->lastname;

		$formData = Array(
							"Merchant_Id" 			=> $Merchant_Id,
							"Amount" 				=> $order->order_subtotal,
							"Order_Id" 				=> $order->order_id,
							"Redirect_Url" 			=> $Redirect_Url,
							"Checksum" 				=> $Checksum,
							"billing_cust_name"		=> $name,
							"billing_cust_address"  => $address,
							"billing_cust_country"  => $country,
							"billing_cust_state"    => $state,
							"billing_cust_city"		=> $city,
							"billing_zip"			=> $zipcode,
							"billing_zip_code"		=> $zipcode,
							"billing_cust_tel"		=> $phone,
							"billing_cust_email"	=> $email,
							"delivery_cust_name"	=> $shippingName,
							"delivery_cust_address"	=> $shippingInfo->address,
							"delivery_cust_country"	=> $shippingInfo->country_code,
							"delivery_cust_state"	=> $state,
							"delivery_cust_tel"		=> $shippingInfo->phone,
							"delivery_cust_notes"	=> $order->customer_note,
							"Merchant_Param"		=> '',
							"delivery_cust_city"	=> $shippingInfo->city,
							"delivery_zip_code"		=> $shippingInfo->zipcode
						);

		$html = '';
		$html .= '<form action="' . $url . '" method="post" name="ccavenueForm" id="ccavenueForm">';
		$html .= '<input type="submit"  value="' . JText::_('PLG_REDSHOP_PAYMENT_CCAVENUE_REDIRECT_MESSAGE') . '" />';

		foreach ($formData as $name => $value)
		{
			$html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
		}

		$html .= '</form></div>';
		$html .= ' <script type="text/javascript">';
		$html .= ' setTimeout(document.ccavenueForm.submit(),500);';
		$html .= ' </script></body></html>';

		echo $html;
	}

	private function generateCheckSum($MerchantId,$Amount,$OrderId ,$URL,$securityToken)
	{
		$str = "$MerchantId|$OrderId|$Amount|$URL|$securityToken";
		$adler = 1;
		$adler = $this->adler32($adler, $str);

		return $adler;
	}

	private function validateCheckSum($MerchantId,$OrderId,$Amount,$AuthDesc,$CheckSum,$securityToken)
	{
		$str = "$MerchantId|$OrderId|$Amount|$AuthDesc|$securityToken";
		$adler = 1;
		$adler = $this->adler32($adler, $str);

		if ($adler == $CheckSum)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	private function adler32($adler , $str)
	{
		$BASE = 65521;
		$s1   = $adler & 0xffff;
		$s2   = ($adler >> 16) & 0xffff;

		for ($i = 0; $i < strlen($str); $i++)
		{
			$s1 = ($s1 + Ord($str[$i])) % $BASE;
			$s2 = ($s2 + $s1) % $BASE;
		}

		return $this->leftshift($s2, 16) + $s1;
	}

	private function leftshift($str , $num)
	{
		$str = DecBin($str);

		for ( $i = 0; $i < (64 - strlen($str)); $i++)
		{
			$str = "0" . $str;
		}

		for ($i = 0; $i < $num; $i++)
		{
			$str = $str . "0";
			$str = substr($str, 1 );
		}

		return $this->cdec($str);
	}

	private function cdec($num)
	{
		$dec = '';

		for ($n = 0; $n < strlen($num); $n++)
		{
			$temp = $num[$n];
			$dec = $dec + $temp * pow(2, strlen($num) - $n - 1);
		}

		return $dec;
	}

	/*
	 *  Plugin onNotifyPayment method with the same name as the event will be called automatically.
	 */
	public function onNotifyPaymentrs_payment_ccavenue($element, $request)
	{
		if ($element != 'rs_payment_ccavenue')
		{
			return false;
		}

		if (!isset($request['Order_Id']))
		{
			return;
		}

		$securityToken     = $this->params->get('security_token', '');
		$billing_cust_name = $request['billing_cust_name'];
		$Merchant_Id       = $request['Merchant_Id'];
		$Amount            = $request['Amount'];
		$Order_Id          = $request['Order_Id'];
		$Merchant_Param    = $request['Merchant_Param'];
		$Checksum          = $request['Checksum'];
		$AuthDesc          = $request['AuthDesc'];
		$emailid           = $request['billing_cust_email'];
		$verify_status     = $this->params->get('verify_status', '');
		$invalid_status    = $this->params->get('invalid_status', '');
		$cancel_status     = $this->params->get('cancel_status', '');

		// Checksum Validation
		$Checksum = $this->validateCheckSum($Merchant_Id, $Order_Id, $Amount, $AuthDesc, $Checksum, $securityToken);
		$order_number = $Order_Id;
		$values = new stdClass;

		if (($Checksum == "true" && $AuthDesc == "Y") || ($Checksum == "true" && $AuthDesc == "B"))
		{
			$values->order_status_code = $verify_status;
			$values->order_payment_status_code = 'Paid';
			$values->log = JText::_('COM_REDSHOP_ORDER_PLACED');
			$values->msg = JText::_('COM_REDSHOP_ORDER_PLACED');
		}
		elseif ($Checksum == "true" && $AuthDesc == "N")
		{
			$values->order_status_code = $invalid_status;
			$values->order_payment_status_code = 'Unpaid';
			$values->log = JText::_('COM_REDSHOP_ORDER_NOT_PLACED');
			$values->msg = JText::_('COM_REDSHOP_ORDER_NOT_PLACED');
		}

		$values->transaction_id = $Merchant_Param;
		$values->order_id = $Order_Id;

		return $values;
	}
}
