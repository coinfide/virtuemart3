<?php

defined ('_JEXEC') or die();

require_once __DIR__ . '/combined.php';

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

if(!class_exists('VmConfig')) {
    require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
}

if(!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentCoinfide extends vmPSPlugin
{
    function __construct (& $subject, $config) {
        parent::__construct ($subject, $config);

        $this->tableFields = array_keys ($this->getTableSQLFields ());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';

        $varsToPush = $this->getVarsToPush ();
        $this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
    }

    public function getVmPluginCreateTableSQL () {

        return $this->createTableSQL ('Coinfide Payment Table');
    }

    function getTableSQLFields () {
        return array (
            'id'                            => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'           => 'int(1) UNSIGNED',
            'order_number'                  => ' char(64)',
            'virtuemart_paymentmethod_id'   => 'mediumint(1) UNSIGNED',
            'payment_name'                  => 'varchar(5000)',
            'payment_order_total'           => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'              => 'char(3) ',
            'cost_per_transaction'          => 'decimal(10,2)',
            'cost_percent_total'            => 'decimal(10,2)',
            'tax_id'                        => 'smallint(1)',
            'user_session'                  => 'varchar(255)'
        );
    }

    public function plgVmOnPaymentNotification()
    {
        if (!class_exists ('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        //both success and cancels go here
        $modelOrder = VmModel::getModel ('orders');

        $orderNumber = $_GET['order'];

        $orderId = VirtueMartModelOrders::getOrderIdByOrderNumber ($orderNumber);

        if (!$orderId) {
            return null;
        }

        $order = $modelOrder->getOrder ($orderId);

        if (!$order) {
            return null;
        }

        //check for current method
        if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL;
        }

        //check type
        if ($_GET['result'] == 'success') {
            //check hash
            $post = $_POST;
            $checksum = $post['checksum'];
            unset($post['checksum']);

            if (md5(http_build_query($post).$method->COINFIDE_PAYMENT_API_SECRET) == $checksum) {
                $order['order_status'] = 'C'; //magic; confirmed
            } else {
                $order['order_status'] = 'X'; //magic; cancelled
            }
        } elseif ($_GET['result'] == 'fail') {
            $order['order_status'] = 'X'; //magic; cancelled
        }

        $modelOrder->updateStatusForOneOrder ($orderId, $order, TRUE);

        $url = JRoute::_("index.php?option=com_virtuemart&view=orders&layout=details&order_number=".$order['details']['BT']->order_number."&order_pass=".$order['details']['BT']->order_pass, false) ;

        $app = JFactory::getApplication();
        $app->redirect($url);
    }

    function plgVmConfirmedOrder ($cart, $order) {
        //check for current method
        if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        /** @var $cart VirtueMartCart */

        //client setup
        $client = new Coinfide\Client();

        $client->setMode($method->COINFIDE_PAYMENT_MODE);
        $client->setCredentials($method->COINFIDE_PAYMENT_API_USERNAME, $method->COINFIDE_PAYMENT_API_PASSWORD);

        //order
        $corder = new \Coinfide\Entity\Order();

        //seller
        $seller = new \Coinfide\Entity\Account();
        $seller->setEmail($method->COINFIDE_PAYMENT_SELLER_EMAIL);

        $corder->setSeller($seller);

        //buyer
        $lang = JFactory::getLanguage();
        $buyer = new \Coinfide\Entity\Account();
        $buyer->setEmail($order['details']['BT']->email);
        $buyer->setName($order['details']['BT']->first_name ?: 'unknown');
        $buyer->setSurname($order['details']['BT']->last_name ?: 'unknown');
        $buyer->setPhone((new \Coinfide\Entity\Phone())->setFullNumber($order['details']['BT']->phone_2));
        $buyer->setLanguage(strtoupper(substr($lang->getTag(), 0, 2)));

        $baddress = new \Coinfide\Entity\Address();
        $baddress->setCity($order['details']['BT']->city);
        $baddress->setFirstAddressLine($order['details']['BT']->address_1);
        $baddress->setSecondAddressLine($order['details']['BT']->address_2);
        $baddress->setPostalCode($order['details']['BT']->zip);
        $baddress->setCountryCode(self::getCountryByID($order['details']['BT']->virtuemart_country_id, 'country_2_code'));
        $baddress->setState(self::getStateByID($order['details']['BT']->virtuemart_state_id));

        $buyer->setAddress($baddress);
        $buyer->setAdditionalInfo($order['details']['BT']->customer_note);

        $corder->setBuyer($buyer);

        //order misc
        $corder->setCurrencyCode(self::getCurrencyByID($order['details']['BT']->order_currency));
        $corder->setExternalOrderId($order['details']['BT']->order_number);

        //success / error callbacks
        $url = JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&method=coinfide';
        $url = $url . '&order=' . $order['details']['BT']->order_number;

        $corder->setSuccessUrl($url . '&result=success');
        $corder->setFailUrl($url . '&result=fail');

        //items
        foreach ($order['items'] as $item) {
            $citem = new \Coinfide\Entity\OrderItem();

            $citem->setName($item->order_item_name ?: 'unknown');
            $citem->setType('I');
            $citem->setQuantity($item->product_quantity);
            $citem->setPriceUnit(round(floatval($item->product_final_price), 2));

            $corder->addOrderItem($citem);
        }

        if ($order['details']['BT']->order_shipment) {
            $sitem = new \Coinfide\Entity\OrderItem();

            $sitem->setName('Shipping'); //todo - hardcode
            $sitem->setType('S');
            $sitem->setQuantity(1);
            $sitem->setPriceUnit(round(floatval($order['details']['BT']->order_shipment + $order['details']['BT']->order_shipment_tax), 2));

            $corder->addOrderItem($sitem);
        }

        $corder->validate();

        $orderInfo = $client->submitOrder($corder);

        $cart = VirtueMartCart::getCart ();
        $cart->emptyCart();

        $app = JFactory::getApplication();
        $app->redirect($orderInfo->getRedirectUrl());
    }

    static public function getCurrencyByID ($id, $fld = 'currency_code_3') {

        if (empty($id)) {
            return '';
        }

        $id = (int)$id;
        $db = JFactory::getDBO ();

        $q = 'SELECT `' . $db->escape ($fld) . '` AS fld FROM `#__virtuemart_currencies` WHERE virtuemart_currency_id = ' . (int)$id;
        $db->setQuery ($q);
        return $db->loadResult ();
    }


    static public function getCountryByID ($id, $fld = 'country_name') {

        if (empty($id)) {
            return '';
        }

        $id = (int)$id;
        $db = JFactory::getDBO ();

        $q = 'SELECT `' . $db->escape ($fld) . '` AS fld FROM `#__virtuemart_countries` WHERE virtuemart_country_id = ' . (int)$id;
        $db->setQuery ($q);
        return $db->loadResult ();
    }

    static public function getStateByID ($id, $fld = 'state_name') {

        if (empty($id)) {
            return '';
        }

        $id = (int)$id;
        $db = JFactory::getDBO ();

        $q = 'SELECT `' . $db->escape ($fld) . '` AS fld FROM `#__virtuemart_states` WHERE virtuemart_state_id = ' . (int)$id;
        $db->setQuery ($q);
        return $db->loadResult ();
    }

    protected function checkConditions ($cart, $method, $cart_prices) {
        return true;
    }

    function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

        return $this->onStoreInstallPluginTable ($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

        return $this->OnSelectCheck ($cart);
    }

    public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

        return $this->displayListFE ($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
        return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
    }

    public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        $this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

        return $this->onShowOrderPrint ($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3( &$data) {
        return $this->declarePluginParams('payment', $data);
    }
    function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

        return $this->setOnTablePluginParams ($name, $id, $table);
    }

}