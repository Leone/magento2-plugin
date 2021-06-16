<?php
/**
 * @author Sendinblue plateform <contact@sendinblue.com>
 * @copyright  2013-2014 Sendinblue
 * URL:  https:www.sendinblue.com
 * Do not edit or add to this file if you wish to upgrade Sendinblue Magento plugin to newer
 * versions in the future. If you wish to customize Sendinblue magento plugin for your
 * needs then we can't provide a technical support.
 **/
namespace Sendinblue\Sendinblue\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sendinblue\Sendinblue\Model;

/**
 * Customer Observer Model
 */
class SibOrderObserver implements ObserverInterface
{
    /**
     * Constant for the plugin name
     */
    const PLUGIN_NAME = "magento_2";

    public function execute(Observer $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $model = $objectManager->create('Sendinblue\Sendinblue\Model\SendinblueSib');
        $apiKeyV3 = $model->getDbData('api_key_v3');
        $trackStatus = $model->getDbData('ord_track_status');
        $dateValue = $model->getDbData('sendin_date_format');
        $orderStatus = $model->getDbData('api_sms_order_status');
        $senderOrder = $model->getDbData('sender_order');
        $senderOrderMessage = $model->getDbData('sender_order_message');
        $order = $observer->getEvent()->getData();
        $orderId = $order['order_ids'][0];
        $orderDatamodel = $objectManager->get('Magento\Sales\Api\Data\OrderInterface')->load($orderId);
        $orderData = $orderDatamodel->getData();
        $email = $orderData['customer_email'];
        $NlStatus = $model->checkNlStatus($email);
        $orderID = $orderData['increment_id'];
        $orderPrice = $orderData['grand_total'];
        $dateAdded = $orderData['created_at'];
        $sibStatus = $model->syncSetting();
        if ($sibStatus == 1) {
            if (!empty($apiKeyV3)) {
                $mailin = $model->createObjSibClient();
            }

            $orderDate = date('Y-m-d', strtotime($dateAdded));

            if ($trackStatus == 1 && $NlStatus == 1 && !empty($apiKeyV3)) {
                $blacklistedValue = 0;
                $attrData = [];
                $attrData['ORDER_DATE'] = $orderDate;
                $attrData['ORDER_PRICE'] = $orderPrice;
                $attrData['ORDER_ID'] = $orderID;
                $attrData['PLUGIN'] = "magento2";
                $attrData['sibInternalSource'] = self::PLUGIN_NAME;
                $dataSync = ["email" => $email,
                "attributes" => $attrData,
                "blacklisted" => false,
                "updateEnabled" => true
                ];
                $mailin->createUser($dataSync);
            }
            if ($orderStatus == 1 && !empty($senderOrder) && !empty($senderOrderMessage)) {
                $custId = $orderData['customer_id'];
                if (!empty($custId)) {
                    $customers = $model->_customers->load($custId);
                    $billingId =  $customers->getDefaultBilling();
                    $billingId = !empty($billingId) ? $billingId : $customers->getDefaultShipping();
                    $address = $objectManager->create('Magento\Customer\Model\Address')->load($billingId);
                }

                $firstname = $address->getFirstname();
                $lastname = $address->getLastname();
                $telephone = !empty($address->getTelephone()) ? $address->getTelephone() : '';
                $countryId = !empty($address->getCountry()) ? $address->getCountry() : '';
                $smsVal = '';
                if (!empty($countryId) && !empty($telephone)) {
                    $countryCode = $model->getCountryCode($countryId);
                    if (!empty($countryCode)) {
                        $smsVal = $model->checkMobileNumber($telephone, $countryCode);
                    }
                }
                $firstName = str_replace('{first_name}', $firstname, $senderOrderMessage);
                $lastName = str_replace('{last_name}', $lastname."\r\n", $firstName);
                $procuctPrice = str_replace('{order_price}', $orderPrice, $lastName);
                $orderDate = str_replace('{order_date}', $orderDate."\r\n", $procuctPrice);
                $msgbody = str_replace('{order_reference}', $orderID, $orderDate);
                $smsData = [];

                if (!empty($smsVal)) {
                    $smsData['to'] = $smsVal;
                    $smsData['from'] = $senderOrder;
                    $smsData['text'] = $msgbody;
                    $smsData['type'] = 'transactional';
                    $model->sendSmsApi($smsData);
                }
            }
        }
        $this->triggerMA($orderId);
    }

    private function triggerMA($orderId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $model = $objectManager->create('Sendinblue\Sendinblue\Model\SendinblueSib');
        $sibAbdStatus = $model->getDbData('sib_abdcart_status');
        $maKey = $model->getDbData('sib_automation_key');

        $orderObj= $objectManager->get('Magento\Sales\Model\Order')->load($orderId);
        $orderData = $orderObj->getData();

        if ( $sibAbdStatus && !empty($maKey) && !empty($orderData) ) {
            $data = array(
                'email' => $orderData["customer_email"],
                'event' => 'order_completed',
                'properties' => array(
                    'FIRSTNAME' => !empty($orderData['customer_firstname']) ? $orderData['customer_firstname'] : '',
                    'LASTNAME' =>  !empty($orderData['customer_lastname']) ? $orderData['customer_lastname'] : ''
                ),
                'eventdata' => array(
                    'id' => "cart:".$orderData['quote_id'],
                    'data' => array()
                )
            );

            $totalDiscount =  !empty($orderData['discount_amount']) ? $orderData['discount_amount'] : 0;
            $totalShipping =  !empty($orderData['shipping_amount']) ? $orderData['shipping_amount'] : 0;
            $revenue = !empty($orderData['grand_total']) ? $orderData['grand_total'] : 0;
            $totalTax =  !empty($orderData['tax_amount']) ? $orderData['tax_amount'] : 0;
            $imageObj = $objectManager->create('\Magento\Framework\UrlInterface');
            $data['eventdata']['data']['id'] = $orderData['increment_id'];
            $data['eventdata']['data']['affiliation'] = $orderData['store_name'];
            $data['eventdata']['data']['date'] = $orderData['created_at'];
            $data['eventdata']['data']['discount'] = $totalDiscount;
            $data['eventdata']['data']['subtotal']= !empty($orderData['subtotal']) ? $orderData['subtotal'] + $totalDiscount : 0;
            $data['eventdata']['data']['shipping'] = $totalShipping;
            $data['eventdata']['data']['tax']= $totalTax;
            $data['eventdata']['data']['revenue'] =  $revenue;
            $data['eventdata']['data']['total_before_tax'] = $revenue - $totalTax;
            $data['eventdata']['data']['currency'] = !empty($orderData['order_currency_code']) ? $orderData['order_currency_code'] : '';
            $data['eventdata']['data']['url'] = $imageObj->getUrl('sales/order');

            $stores = $model->_storeManagerInterface->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            $allProducts = array();
            foreach ($orderObj->getAllVisibleItems() as $item) {
                $productData = $item->getData();
                if( !empty($productData) ) {
                    $product = $objectManager->create('Magento\Catalog\Model\Product')->load($productData['product_id']);
                    $allProducts[] = array(
                        "name" => !empty($productData['name']) ? $productData['name'] : '',
                        "sku" => !empty($productData['sku']) ? $productData['sku'] : '',
                        "category" => !empty($productData['product_type']) ? $productData['product_type'] : '',
                        "id" => !empty($productData['product_id']) ? $productData['product_id'] : '',
                        "variant_id" => '',
                        "variant_id_name" => '',
                        "price" => $productData['price'],
                        "quantity" => !empty($productData['qty_ordered']) ? $productData['qty_ordered']:1,
                        "url" => $product->getProductUrl(),
                        "image" => !empty($product->getImage()) ? $stores. "catalog/product" . $product->getImage() : "NA"
                    );
                }
            }

            $data['eventdata']['data']['items'] = $allProducts;

            $shippingAddressObj = $orderObj->getShippingAddress();
            $billingAddressObj = $orderObj->getBillingAddress();
            $address = array();
            
            if ( !empty($shippingAddressObj) ) {
                $address['shippingAddress'] = $shippingAddressObj->getData();
                $data['eventdata']['data']['shipping_address'] = array(
                    'firstname' => !empty($address['shippingAddress']['firstname']) ? $address['shippingAddress']['firstname'] : '',
                    'lastname' => !empty($address['shippingAddress']['lastname']) ? $address['shippingAddress']['lastname'] : '',
                    'company' => !empty($address['shippingAddress']['company']) ? $address['shippingAddress']['company'] : '',
                    'phone' => !empty($address['shippingAddress']['telephone']) ? $address['shippingAddress']['telephone'] : '',
                    'country' => !empty($address['shippingAddress']['country_id']) ? $address['shippingAddress']['country_id'] : '',
                    'state' => !empty($address['shippingAddress']['region']) ? $address['shippingAddress']['region'] : '',
                    'address1' => !empty($address['shippingAddress']['street']) ? $address['shippingAddress']['street'] : '',
                    'address2' => !empty($address['shippingAddress']['street']) ? $address['shippingAddress']['street'] : '',
                    'city' => !empty($address['shippingAddress']['city']) ? $address['shippingAddress']['city'] : '',
                    'zipcode' => !empty($address['shippingAddress']['postcode']) ? $address['shippingAddress']['postcode'] : ''
                );
            }

            if ( !empty($billingAddressObj) ) {
                $address['billingAddress'] = $billingAddressObj->getData();
                $data['eventdata']['data']['billing_address'] = array(
                    'firstname' => !empty($address['billingAddress']['firstname']) ? $address['billingAddress']['firstname'] : '',
                    'lastname' => !empty($address['billingAddress']['lastname']) ? $address['billingAddress']['lastname'] : '',
                    'company' => !empty($address['billingAddress']['company']) ? $address['billingAddress']['company'] : '',
                    'phone' => !empty($address['billingAddress']['telephone']) ? $address['billingAddress']['telephone'] : '',
                    'country' => !empty($address['billingAddress']['country_id']) ? $address['billingAddress']['country_id'] : '',
                    'state' => !empty($address['billingAddress']['region']) ? $address['billingAddress']['region'] : '',
                    'address1' => !empty($address['billingAddress']['street']) ? $address['billingAddress']['street'] : '',
                    'address2' => !empty($address['billingAddress']['street']) ? $address['billingAddress']['street'] : '',
                    'city' => !empty($address['billingAddress']['city']) ? $address['billingAddress']['city'] : '',
                    'zipcode' => !empty($address['billingAddress']['postcode']) ? $address['billingAddress']['postcode'] : ''
                );   
            }

            $mailin = $model->createObjSibClient();
            $mailin->curlPostAbandonedEvents($data, $maKey);
        }
    }
}
