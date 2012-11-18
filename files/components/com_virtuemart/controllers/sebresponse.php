<?php

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Load the controller framework
jimport('joomla.application.component.controller');

class VirtueMartControllerSebresponse extends JController {

    public function __construct() {
		parent::__construct();
    }

    function sebResponseReceived() {
		$this->PaymentResponseReceived();
		$this->ShipmentResponseReceived();
    }

    function PaymentResponseReceived() {

	if (!class_exists('vmPSPlugin'))
	    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php'); JPluginHelper::importPlugin('vmpayment');

		$return_context = "";
		$dispatcher = JDispatcher::getInstance();
		$html = "";
		$paymentResponse = Jtext::_('COM_VIRTUEMART_CART_THANKYOU');
		$returnValues = $dispatcher->trigger('plgVmOnPaymentResponseReceived', array( 'html' => &$html,&$paymentResponse));
	
		$view = $this->getView('sebresponse', 'html');
		$layoutName = JRequest::getVar('layout', 'default');
		$view->setLayout($layoutName);
		
		$macFields = array ();

	    foreach ((array)$_REQUEST as $f => $v) {
	        if (substr ($f, 0, 3) == 'VK_') {
	            $macFields[$f] = $v;
	        }
	    }
	    
	    $model = VmModel::getModel('orders');
	    $order = array() ;
	    $message = "";
		$virtuemart_order_id = (int)$macFields['VK_STAMP'];
		$order[$virtuemart_order_id] = $model->getOrder($virtuemart_order_id);
		
		$db = JFactory::getDBO ();
		$q = "SELECT `payment_params` FROM  `#__virtuemart_paymentmethods` WHERE  `payment_element` = 'seb' ";
		
		$db->setQuery ($q);
		$result = $db->loadResult();
		
		preg_match('(bank_certificate=[^|]+)i', $result, $match);
		$bank_certificate = split('=', $match[0]); //str_replace("\"", "", split('=', $match[0]));
		$pat = array("\"", "\\");
		$bank_certificate = str_replace($pat, "", $bank_certificate[1]);
	
		preg_match('(my_id=[^|]+)i', $result, $match2);
		$my_id = str_replace("\"", "", split('=', $match2[0]));
		
	    $key = openssl_pkey_get_public(file_get_contents("$bank_certificate"));
	
	    if (!openssl_verify($this->generateMACString($macFields), base64_decode($macFields['VK_MAC']), $key)) {
	        trigger_error ("Invalid signature", E_USER_ERROR);
	    }
	    
	    /* Update the statuses */
	    
		if ($macFields['VK_SERVICE'] == '1901') {
    		$order[$virtuemart_order_id]['order_status'] = 'X';
			//$order[$virtuemart_order_id]['order_status_name'] = 'Canceled';
			//[order_status_code] = 'X'
			
			$message .= "<b>Transaction canceled.</b><br/><br/>";
			$message .= "Order ID: ".$macFields['VK_STAMP']."<br/>";
            $message .= "Error code: ".$macFields['VK_SERVICE']."<br/>";
			$message .= "Payer: ".$macFields['VK_SND_NAME']."<br/>";
			$message .= "Payer account: ".$macFields['VK_SND_ACC']."<br/>";
			$message .= ": ".$macFields['VK_AMOUNT']." ".$macFields['VK_CURR']."<br/>";
			$message .= "Date: ".$macFields['VK_T_DATE']."<br/>";
			
    	} else if ($macFields['VK_SERVICE'] == '1101') {
        	if ($this->from_banklink_ch($macFields['VK_REC_ID']) != $my_id[1]) {
        		$order[$virtuemart_order_id]['order_status'] = 'X';
				//$order[$virtuemart_order_id]['order_status_name'] = 'Canceled';
				//[order_status_code] = 'X'
			
	            $message .= "<b>Transaction canceled.</b><br/><br/>";
				$message .= "Order ID: ".$macFields['VK_STAMP']."<br/>";
	            $message .= "Error code: ".$macFields['VK_SERVICE']."<br/>";
				$message .= "Payer: ".$macFields['VK_SND_NAME']."<br/>";
				$message .= "Payer account: ".$macFields['VK_SND_ACC']."<br/>";
				$message .= "Amount: ".$macFields['VK_AMOUNT']." ".$macFields['VK_CURR']."<br/>";
				$message .= "Date: ".$macFields['VK_T_DATE']."<br/>";
				
    		} else { // OK Confirmed Order
    			$order[$virtuemart_order_id]['order_status'] = 'C';
				//$order[$virtuemart_order_id]['order_status_name'] = 'Confirmed';
				//[order_status_code] = 'C'
			
	        	$message .= "<b>The transaction was successful!</b><br/><br/>";
				$message .= "Order ID: ".$macFields['VK_STAMP']."<br/>";
	            $message .= "Payer: ".$macFields['VK_SND_NAME']."<br/>";
				$message .= "Payer account: ".$macFields['VK_SND_ACC']."<br/>";
				$message .= "Amount: ".$macFields['VK_AMOUNT']." ".$macFields['VK_CURR']."<br/>";
				$message .= "Date: ".$macFields['VK_T_DATE']."<br/>";   	
			}
			
	    } else {
	    	$order[$virtuemart_order_id]['order_status'] = 'X';
			//$order[$virtuemart_order_id]['order_status_name'] = 'Canceled';
			//[order_status_code] = 'X'
			
		    $message .= "<b>Transaction canceled.</b><br/><br/>";
			$message .= "Order ID: ".$macFields['VK_STAMP']."<br/>";
	        $message .= "Error code: ".$macFields['VK_SERVICE']."<br/>";
			$message .= "Payer: ".$macFields['VK_SND_NAME']."<br/>";
			$message .= "Payer account: ".$macFields['VK_SND_ACC']."<br/>";
			$message .= "Amount: ".$macFields['VK_AMOUNT']." ".$macFields['VK_CURR']."<br/>";
			$message .= "Date: ".$macFields['VK_T_DATE']."<br/>";
    	}
	    
		$result = $model->updateOrderStatus($order);
		
		$view->assignRef('paymentResponse', $paymentResponse);
	   	$view->assignRef('paymentResponseHtml', $html);
	   	$view->assignRef('message', $message);
	   	//$view->assignRef('macFields', $macFields);
	   	//$view->assignRef('orders', $order);
	
		// Display it all
		$view->display();
    }
    
    function generateMACString($macFields) {
    	$banklinkCharset = $macFields['VK_CHARSET'];
        $requestNum = $macFields['VK_SERVICE'];
		$variableOrder = $this->variableOrder();

		$data = '';

        foreach ((array)$variableOrder[$requestNum] as $key) {
            $v = $macFields[$key];
            $l = strlen ($v);
            $data .= str_pad ($l, 3, '0', STR_PAD_LEFT) . $v;
        }

        if($data == '')
        	return 'null';
        else
        	return $data;
    }
    
	function variableOrder() {
	    $VK_variableOrder = array(
	        1001 => array(
	            'VK_SERVICE','VK_VERSION','VK_SND_ID',
	            'VK_STAMP','VK_AMOUNT','VK_CURR',
	            'VK_ACC','VK_NAME','VK_REF','VK_MSG'
	        ),

	        1101 => array(
	            'VK_SERVICE','VK_VERSION','VK_SND_ID',
	            'VK_REC_ID','VK_STAMP','VK_T_NO','VK_AMOUNT','VK_CURR',
	            'VK_REC_ACC','VK_REC_NAME','VK_SND_ACC','VK_SND_NAME',
	            'VK_REF','VK_MSG','VK_T_DATE'
	        ),

	        1901 => array(
	            'VK_SERVICE','VK_VERSION','VK_SND_ID',
	            'VK_REC_ID','VK_STAMP','VK_REF','VK_MSG'
	        ),
	    );
	    return $VK_variableOrder;
    }
    
    function from_banklink_ch($v) {
    	return mb_convert_encoding($v, 'utf-8', 'utf-8');
    }

    function ShipmentResponseReceived() {
		// TODO: not ready yet

	    if (!class_exists('vmPSPlugin'))
		    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
		    JPluginHelper::importPlugin('vmshipment');
	
		    $return_context = "";
		    $dispatcher = JDispatcher::getInstance();
	
		    $html = "";
		    $shipmentResponse = Jtext::_('COM_VIRTUEMART_CART_THANKYOU');
		    $dispatcher->trigger('plgVmOnShipmentResponseReceived', array( 'html' => &$html,&$shipmentResponse));
    }

    /**
     * PaymentUserCancel()
     * From the payment page, the user has cancelled the order. The order previousy created is deleted.
     * The cart is not emptied, so the user can reorder if necessary.
     * then delete the order
     * @author Valerie Isaksen
     *
     */
    function pluginUserPaymentCancel() {

		if (!class_exists('vmPSPlugin'))
		    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
	
		if (!class_exists('VirtueMartCart'))
		    require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
	
		JPluginHelper::importPlugin('vmpayment');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('plgVmOnUserPaymentCancel', array());
	
		// return to cart view
		$view = $this->getView('cart', 'html');
		$layoutName = JRequest::getWord('layout', 'default');
		$view->setLayout($layoutName);
	
		// Display it all
		$view->display();
    }

    /**
     * Attention this is the function which processs the response of the payment plugin
     *
     * @author Valerie Isaksen
     * @return success of update
     */
    function pluginNotification() {

		if (!class_exists('vmPSPlugin'))
		    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
	
		if (!class_exists('VirtueMartCart'))
		    require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
	
		if (!class_exists('VirtueMartModelOrders'))
		    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
	
		JPluginHelper::importPlugin('vmpayment');
	
		$dispatcher = JDispatcher::getInstance();
		$returnValues = $dispatcher->trigger('plgVmOnPaymentNotification', array());
    }
}

//pure php no Tag
