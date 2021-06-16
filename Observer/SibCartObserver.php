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
class SibCartObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $model = $objectManager->create('Sendinblue\Sendinblue\Model\SendinblueSib');
        $maKey = $model->getDbData('sib_automation_key');
        $sibAbdStatus = $model->getDbData('sib_abdcart_status');
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
        $customer = $cart->getQuote()->getData();

        if ( empty($maKey) || !$sibAbdStatus || empty($customer) || empty($customer["customer_email"]) ) {
            return false;
        }

        $allProducts = array();
        $stores = $model->_storeManagerInterface->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        
        foreach ($cart->getQuote()->getAllVisibleItems() as $item) {
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
                            "price" => ( array_key_exists("price", $productData) ? $productData["price"] : "NA" ),
                            "quantity" => $productData['qty'],
                            "url" => $product->getProductUrl(),
                            "image" => !empty($product->getImage()) ? $stores. "catalog/product" . $product->getImage() : "NA"
                        );
            }
        }

        if( empty($allProducts) ) {
            $data = array(
                'email' => $customer["customer_email"],
                'event' => 'cart_deleted',
                'properties' => array(
                    'FIRSTNAME' => !empty($customer['customer_firstname']) ? $customer['customer_firstname'] : '',
                    'LASTNAME' =>  !empty($customer['customer_lastname']) ? $customer['customer_lastname'] : ''
                ),
                'eventdata' => array(
                    'id' => "cart:".$customer['entity_id'],
                    'data' => array("items" => array())
                )
            );
            $mailin = $model->createObjSibClient();
            $mailin->curlPostAbandonedEvents($data, $maKey);
            return;
        }

        $revenue = !empty($customer['grand_total']) ? $customer['grand_total'] : 0;
        $totalTax =  !empty($customer['tax_amount']) ? $customer['tax_amount'] : 0;
        $discount = !empty($customer['subtotal_with_discount']) && !empty($customer['subtotal']) ? $customer['subtotal'] - $customer['subtotal_with_discount'] : 0;
        $data = array(
                'email' => $customer["customer_email"],
                'event' => 'cart_updated',
                'properties' => array(
                    'FIRSTNAME' => !empty($customer['customer_firstname']) ? $customer['customer_firstname'] : '',
                    'LASTNAME' =>  !empty($customer['customer_lastname']) ? $customer['customer_lastname'] : ''
                ),
                'eventdata' => array(
                    'id' => "cart:".$customer['entity_id'],
                    'data' => array()
                )
            );
        $data['eventdata']['data']['items'] = $allProducts;
        $data['eventdata']['data']['affiliation'] = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore($customer['store_id'])->getName();
        $data['eventdata']['data']['subtotal'] = !empty($customer['subtotal']) ? $customer['subtotal'] : 0;
        $data['eventdata']['data']['discount'] = $discount;
        $data['eventdata']['data']['shipping'] = !empty($customer['shipping_amount']) ? $customer['shipping_amount'] : 0;
        $data['eventdata']['data']['total_before_tax'] = $revenue - $totalTax;
        $data['eventdata']['data']['tax'] = $totalTax;
        $data['eventdata']['data']['total'] = $revenue;
        $data['eventdata']['data']['currency'] = !empty($customer['quote_currency_code']) ? $customer['quote_currency_code'] : 0;
        $imageInterfaceObj = $objectManager->create('\Magento\Framework\UrlInterface');
        $data['eventdata']['data']['url'] = $imageInterfaceObj->getUrl('checkout/cart');

        $mailin = $model->createObjSibClient();
        $mailin->curlPostAbandonedEvents($data, $maKey);
    }
}
