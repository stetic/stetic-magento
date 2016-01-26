<?php

/**
 * Stetic Analytics Magento Extension
 *
 * @category    Stetic
 * @package     Stetic_Analytics
 * @copyright   Copyright (c) 2014 Stetic (http://www.stetic.com/)
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

class Stetic_Analytics_Model_Observer extends Varien_Event_Observer
{
    /**
     * Events to listen on controller action predispatch
     */
    private $trigger_actions = array(
        "checkout_cart_add",
        "checkout_cart_delete",
        "checkout_cart_updatePost",
        "checkout_cart_estimatePost",
        "checkout_cart_couponPost",
        "checkout_onepage_index",
        "checkout_onepage_savePayment",
        "checkout_onepage_saveOrder",
        "firecheckout_index_saveOrder",
        "checkout_multishipping_success",
        "sales_order_reorder",
        "sales_order_print",
        "customer_account_create",
        "customer_account_createpost",
        "customer_account_editPost",
        "customer_account_loginPost",
        "customer_account_logoutSuccess",
        "customer_account_forgotpasswordpost",
        "customer_account_resetpasswordpost",
        "customer_address_formPost",
        "wishlist_index_add",
        "wishlist_index_remove",
        "wishlist_index_cart",
        "wishlist_index_update",
        "sendfriend_product_sendmail",
        "catalog_product_compare_add",
        "catalog_product_compare_remove",
        "catalogsearch_result_index",
        "catalogsearch_advanced_result",
        "contacts_index_post",
        "review_product_post",
        "cms_index_noRoute",
    );
    
    /**
     * Controller Action Predispatch Observer
     *
     * @param Varien_Event_Observer $observer
     */
    public function controllerActionPredispatch(Varien_Event_Observer $observer)
    {
        $request = $observer->getEvent()->getControllerAction()->getRequest()->getParams();
        $action = $observer->getEvent()->getControllerAction()->getFullActionName();
        
        if(in_array($action, $this->trigger_actions))
        {
            $trigger = array("action" => $action, "request" => $request);
            
            if($action == 'checkout_cart_delete')
            {
                $item = Mage::getModel('checkout/cart')->getQuote()->getItemById($request['id']);
                $trigger['product_id'] = $item->getProduct()->getId();
                $trigger['options'] = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
                $trigger['quantity'] = $item->getQty();
            }
            elseif($action == 'wishlist_index_remove' || $action == 'wishlist_index_cart')
            {
                $trigger['product_id'] = Mage::getModel('wishlist/item')->load($request['item'])->getProduct()->getId();
            }
            
            $this->_addTrigger($trigger);
        }
    }
    
    /**
     * Checkout Multishipping Controller Success Action Observer
     *
     * @param Varien_Event_Observer $observer
     */
    public function checkoutMultishippingControllerSuccessAction(Varien_Event_Observer $observer)
    {
        $data = $observer->getData();
        
        $this->_addTrigger(array(
            'action' => 'checkoutMultishippingControllerSuccessAction',
            'request' => $data,
        ));
    }
    
    /**
     * Newsletter Subscriber Save After Observer
     *
     * @param Varien_Event_Observer $observer
     */
    public function newsletterSubscriberSaveAfter(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $subscriber = $event->getDataObject();
        $data = $subscriber->getData();
        
        $this->_addTrigger(array(
            'action' => 'newsletterSubscriberSaveAfter',
            'request' => $data,
            'status_change' => $subscriber->getIsStatusChanged(),
        ));
    }
    
    /**
     * Adds a trigger
     *
     * @param array $trigger
     */
    private function _addTrigger($trigger)
    {
        $existing_triggers = Mage::getSingleton('core/session')->getData('stetic_event_trigger');
        if(!$existing_triggers)
        {
            $existing_triggers = array();
        }
        $existing_triggers[] = $trigger;
        
        Mage::getSingleton('core/session')->setData('stetic_event_trigger', $existing_triggers);
    }
}