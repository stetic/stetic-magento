<?php

/**
 * Stetic Analytics Magento Extension
 *
 * @category    Stetic
 * @package     Stetic_Analytics
 * @copyright   Copyright (c) 2014 Stetic (http://www.stetic.com/)
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

class Stetic_Analytics_Block_Tracker extends Mage_Core_Block_Template
{
    /**
     * Constructor
     */
    public function _construct()
    {
        parent::_construct();

        if(Mage::helper('Stetic')->isEnabled() == true)
        {
            $this->setTemplate('stetic/tracker.phtml');
        }
    }
    
    /**
     * Returns whenever this extension is enabled or disabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::helper('Stetic')->isEnabled();
    }
    
    /**
     * Returns site token from config
     *
     * @return string
     */
    public function getSiteToken()
    {
        return Mage::helper('Stetic')->getSiteToken();
    }
    
    /**
     * Returns cookie domain from config
     *
     * @return string
     */
    public function getCookieDomain()
    {
        return Mage::helper('Stetic')->getCookieDomain();
    }
    
    /**
     * Returns whenever order tracking is enabled or disabled.
     *
     * @return string
     */
    public function isOrderTrackingEnabled()
    {
        return ($this->isEnabled() && self::getConfig('advanced/order_tracking'));
    }
    
    /**
     * Identify customers
     *
     * @return string
     */
    public function identify()
    {
        if(self::getConfig('advanced/identify_loggedin') && Mage::getSingleton('customer/session')->isLoggedIn())
        {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            if($customer)
            {
                $identify = array(
                    'id' => $customer->getId(),
                    'name' => $customer->getName(),
                    'email' => $customer->getEmail(),
                    'created_at' => date("Y-m-d H:i:s", $customer->getCreatedAtTimestamp()),
                );

                if(!$address = $customer->getDefaultBillingAddress())
                {
                    $address = $customer->getDefaultShippingAddress();
                }

                if($address)
                {
					/***
					* Check whenever customers from specific countries should be excluded
					*/
					if(self::getConfig('advanced/identify_loggedin_disallow_specific'))
					{
						$disabled_countries = explode(",", self::getConfig('advanced/identify_loggedin_disallow_specificcountry'));
						if(!empty($disabled_countries) && $address->getCountryId() && in_array($address->getCountryId(), $disabled_countries))
						{
   							//self::log("Disabled country " . $address->getCountryId(), null, 'stetic.log');
							return '';
						}
					}

                    $identify['company'] = $address->getCompany();
                    $identify['phone'] = $address->getTelephone();
                    $identify['city'] = $address->getCity();
                    $identify['country'] = $address->getCountryId();
                }
                return '_fss.identify = ' . json_encode($identify) . ';' . PHP_EOL;
            }
        }
        return '';
    }
    
    /**
     * Event tracking functions
     *
     * @return string
     */
    public function trackEvents()
    {
        if($triggers = Mage::getSingleton('core/session')->getData('stetic_event_trigger'))
        {
            // Unset used trigger
            Mage::getSingleton('core/session')->unsetData('stetic_event_trigger');

			$html_result = array();
			
			foreach($triggers as $trigger)
			{
	            $action = $trigger["action"];
	            $request = $trigger["request"];
            
				//self::log($action, $trigger);
            
	            /***
	            * Cart add
	            */
	            if( $action == 'checkout_cart_add' && self::getConfig('advanced/cart_tracking') )
	            {
					$product_id = $request['product'];
					
	                if($product_id && $product_data = $this->get_product_from_request($product_id, $request))
	                {
	                    $product_data['quantity'] = ($request['qty']) ? (int)$request['qty'] : 1;
										
						if(self::getConfig('advanced/track_products_with_options') && !empty($request['super_attribute']) )
						{
							$product = Mage::getModel('catalog/product')->load($product_id);
							$child_product = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($request['super_attribute'], $product);
							
							if($child_product && $child_product->getId())
							{
								$attributes = $child_product->getAttributes();

								if($attributes && !empty($attributes))
								{
									$product_data['options'] = array();
					                foreach ($attributes as $attribute)
					                {
										if ($attribute->getIsUserDefined() && $attribute->getIsVisibleOnFront() && $attribute->getIsConfigurable())
										{
											$label = $attribute->getFrontend()->getLabel($child_product);		
											$value = $attribute->getFrontend()->getValue($child_product);
											$product_data['options'][$label] = $value;  
										}
					                }
								}
							}
						}
					

	                    $html_result[] = $this->trackEvent('basket', array("product" => $product_data));
	                }
	            }
	            /***
	            * Cart delete
	            */
	            elseif( $action == 'checkout_cart_delete' && self::getConfig('advanced/cart_tracking') )
	            {
				  	if($product_id = $trigger["product_id"])
					{
						$quantity = (isset($trigger['quantity'])) ? (int)$trigger['quantity'] : 1;
						$request['qty'] = $quantity;
						
		                if($product_data = $this->get_product_from_request($product_id, $request))
		                {
							if(self::getConfig('advanced/track_products_with_options'))
							{
								if($options = $trigger['options'])
								{
									$product_data['quantity'] = $quantity;
								
									$product_data['options'] = array();
					                foreach ($options['attributes_info'] as $option)
					                {
					                    $product_data['options'][$option['label']] = $option['value'];    
					                }

					                if($product_data['price'] == 0 && $options['bundle_options'] && is_array($options['bundle_options']))
					                {
					                	$price = 0;
					                	foreach($options['bundle_options'] as $bundle_option)
					                	{
					                		if($bundle_option['value'] && is_array($bundle_option['value']))
					                		{
					                			foreach($bundle_option['value'] as $value)
					                			{
					                				if(isset($value['price']))
					                				{
					                					$qty = ($value['qty']) ? (int)$value['qty'] : 1;
					                					$price += ($qty*(float)$value['price']);
					                				}
					                			}
					                		}
					                	}
					                	$product_data['price'] = $price;
					                }
								}
							}
					
		                    $html_result[] = $this->trackEvent('basket_remove', array("product" => $product_data));
		                }
					}
	            }
	            /***
	            * Cart coupon post
	            */
	            elseif( $action == 'checkout_cart_couponPost' && self::getConfig('advanced/cart_tracking') )
	            {
	            	$coupon = Mage::getModel('salesrule/coupon')->load($request['coupon_code'], 'code');
					
					$event = ($request['remove']) ? 'coupon_remove' : 'coupon';
					$status = 'invalid';
					
					$event_properties = array(
           				'code' => $request['coupon_code'],
           				'status' => $status,
           			);
					
					if($coupon && $coupon->getCouponId())
					{
						$quote = Mage::getSingleton('checkout/cart')->getQuote();
						$used_coupon_code = $quote->getCouponCode();
						$event_properties['status'] = ($used_coupon_code == $request['coupon_code']) ? 'valid' : 'invalid';

						$rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
						if($rule && $rule->getRuleId())
						{
							$rule_data = $rule->getData();
							$event_properties['name'] = $rule_data['name'];
						}
					}
					
					
					$html_result[] = $this->trackEvent($event, $event_properties);
	            }
	            /***
	            * Cart estimate post
	            */
	            elseif( $action == 'checkout_cart_estimatePost' && self::getConfig('advanced/cart_tracking') )
	            {
	            	$country = Mage::getModel('directory/country')->load($request['country_id']);
	            	$region = Mage::getModel('directory/region')->load($request['region_id']);

           			$html_result[] = $this->trackEvent('basket_estimate_shipping', array(
           				'country' => $country->getName(),
           				'region' => $region->getName(),
           				'zip' => $request['estimate_postcode'],
           				'city' => $request['estimate_city'],
           			));
	            }
	            /***
	            * Cart update
	            */
	            elseif( $action == 'checkout_cart_updatePost' && self::getConfig('advanced/cart_tracking') )
	            {
           			$html_result[] = $this->trackEvent('basket_update');
	            }
	            /***
	            * Checkout index
	            */
	            elseif( $action == 'checkout_onepage_index' && $this->isOrderTrackingEnabled() )
	            {
	                $html_result[] = $this->trackEvent('checkout_index');
	            }
	            /***
	            * Checkout success
	            */
	            elseif( ($action == 'checkout_onepage_saveOrder' || $action == 'checkout_multishipping_success' || $action == 'checkoutMultishippingControllerSuccessAction') && $this->isOrderTrackingEnabled() )
	            {
	            	$orderIds = array();
	            	/***
	            	 * Multishipping can have more than one id
	            	 */
	            	if($action == 'checkoutMultishippingControllerSuccessAction')
	            	{
	            		$orderIds = $request['order_ids'];
	            	}
	            	if(empty($orderIds))
	            	{
	            		$orderIds = array(Mage::getSingleton('checkout/session')->getLastOrderId());
	            	}
	                
	                $collection = Mage::getResourceModel('sales/order_collection')->addFieldToFilter('entity_id', array('in' => $orderIds));
        			$result = array();

	            	foreach ($collection as $order)
	            	{
                        $order_properties = array(
                            "id" => $order->getIncrementId(),
                            "total" => (float)$order->getGrandTotal(),
                            "sub_total" => (float)$order->getSubtotal(),
                            "coupon" => (string)$order->getCouponCode(),
                            "quantity" => (int)$order->getTotalQtyOrdered(),
                            "weight" => (float)$order->getWeight(),
                            "discount" => (float)$order->getDiscountAmount(),
                            "shipping" => array("type" => $order->getShippingDescription(), "amount" => (float)$order->getShippingAmount()),
                        );
                    
                        if($payment = $order->getPayment()->getMethodInstance())
                        {
                            if($payment_amount = $payment->getAmount())
                            {
                                $order_properties['payment'] = array("type" => $payment->getTitle(), "amount" => (float)$payment_amount);
                            }
                            else
                            {
                                $order_properties['payment'] = $payment->getTitle();
                            }
                        }
                    
                        $total_revenue = (float)$order->getSubtotal()+(float)$order->getDiscountAmount();

                        $products = array();
                        $order_items = $order->getAllVisibleItems();
                        
                        foreach($order_items as $item_id => $item)
                        {
                            $product = Mage::getModel("catalog/product")->load($item->getProductId());
                            $quantity = ((int)$item->getQtyOrdered() > 0) ? (int)$item->getQtyOrdered() : 1;
                            $cost = $product->getCost();
                            $total_revenue -= $cost;
                        
                            $categories = array();                
                            $category_ids = $product->getCategoryIds();
                            foreach($category_ids as $category_id)
                            {
                                $category = Mage::getModel('catalog/category')->load($category_id) ;
                                $categories[] = $category->getName();
                            } 
                        
                            $product_for_products = array(
                                "id" => $item->getProductId(),
                                "name" => $item->getName(),
                                "sku" => $item->getSku(),
                                "quantity" => (int)$item->getQtyOrdered(),
                                "price" => (float)$item->getPrice(),
                                "cost" => (float)$cost,
                                "revenue" => (float)(($item->getPrice()-$cost)*$quantity),
                                "category" => $categories,
                            );
                        
							/***
							* We have a configurable product
							*/
                            if($product->getTypeId() == 'configurable')
                            {
                                if($product_options = $item->getProductOptions())
								{
									/***
									* Add options to order product if enabled in settings
									*/
									if(self::getConfig('advanced/track_products_with_options'))
									{
										$product_for_products['options'] = array();
		                                foreach ($product_options['attributes_info'] as $option)
		                                {
		                                    $product_for_products['options'][$option['label']] = $option['value'];    
		                                }
									}									
								}
                            }
                        
                            $products[] = $product_for_products;
                        }
                    
                        $order_properties['products'] = $products;
                        $order_properties['revenue'] = (float)$total_revenue;
                    
                        $html_result[] = $this->trackEvent('order', $order_properties);
	            	}
	            }
				/***
				* Reorder
				*/
	            elseif( $action == 'sales_order_reorder' && $this->isOrderTrackingEnabled() )
	            {
	                $html_result[] = $this->trackEvent('reorder', array('order_id' => $request['order_id']));
	            }
				/***
				* Order print
				*/
	            elseif( $action == 'sales_order_print' && $this->isOrderTrackingEnabled() )
	            {
	                $html_result[] = $this->trackEvent('order_print', array('order_id' => $request['order_id']));
	            }
				/***
				* Wishlist add
				*/
	            elseif( $action == 'wishlist_index_add' && self::getConfig('advanced/wishlist_tracking') && $request && $request['product'] )
	            {
	                if($product_data = $this->get_product_from_request($request['product'], $request))
	                {
	                    $html_result[] = $this->trackEvent('wishlist', array("product" => $product_data));
	                }
	            }
				/***
				* Wishlist delete
				*/
	            elseif( $action == 'wishlist_index_remove' && self::getConfig('advanced/wishlist_tracking') && $request)
	            {
					$product_id = $trigger['product_id'];

	                if($product_id && $product_data = $this->get_product_from_request($product_id, $request))
	                {
	                    $html_result[] = $this->trackEvent('wishlist_remove', array("product" => $product_data));
	                }
	            }
				/***
				* Wishlist to cart
				*/
	            elseif( $action == 'wishlist_index_cart' && self::getConfig('advanced/wishlist_tracking') && $request)
	            {
					$product_id = $trigger['product_id'];

	                if($product_id && $product_data = $this->get_product_from_request($product_id, $request))
	                {
	                    $html_result[] = $this->trackEvent('wishlist_cart', array("product" => $product_data));
	                }
	            }
				/***
				* Wishlist update
				*/
	            elseif( $action == 'wishlist_index_update' && self::getConfig('advanced/wishlist_tracking') && $request)
	            {
					$html_result[] = $this->trackEvent('wishlist_update');
	            }
				/***
				* Compare add
				*/
	            elseif( $action == 'catalog_product_compare_add' && self::getConfig('advanced/product_compare_tracking') && $request && $request['product'] )
	            {
	                if($product_data = $this->get_product_from_request($request['product'], $request))
	                {
	                    $html_result[] = $this->trackEvent('compare', array("product" => $product_data));
	                }
	            }
				/***
				* Compare delete
				*/
	            elseif( $action == 'catalog_product_compare_remove' && self::getConfig('advanced/product_compare_tracking') && $request && $request['product'] )
	            {					
	                if($product_data = $this->get_product_from_request($request['product'], $request))
	                {
	                    $html_result[] = $this->trackEvent('compare_remove', array("product" => $product_data));
	                }
	            }
				/***
				* Sendfriend
				*/
	            elseif( $action == 'sendfriend_product_sendmail' && self::getConfig('advanced/sendfriend_tracking') )
	            {
					$product_id = $request['id'];

	                if($product_id && $product_data = $this->get_product_from_request($product_id, $request))
	                {
	                    $html_result[] = $this->trackEvent('sendfriend', array("product" => $product_data));
	                }
	            }
				/***
				* Account create view
				*/
				/*
	            elseif( $action == 'customer_account_create' && self::getConfig('advanced/account_create_tracking') )
	            {
	                $html_result[] = $this->trackEvent('account_create_view');
	            }
				*/
				/***
				* Account create post
				*/
	            elseif( $action == 'customer_account_createpost' && self::getConfig('advanced/account_create_tracking') )
	            {
	                $html_result[] = $this->trackEvent('account_create', array(
	                	'firstname' => $request['firstname'],
	                	'lastname' => $request['lastname'],
	                	'email' => $request['email'],
	                	'newsletter' => (bool)($request['is_subscribed']),
	                ));
					
					if( (bool)$request['is_subscribed'] === true )
					{
		                $html_result[] = $this->trackEvent('newsletter_subscribe', array(
		                	'email' => $request['email'],
		                ));
					}
	            }
				/***
				* Login
				*/
	            elseif( $action == 'customer_account_loginPost' && self::getConfig('advanced/login_tracking') )
	            {
					$username = (isset($request['login']) && isset($request['login']['username'])) ? $request['login']['username'] : '';
					if( Mage::getSingleton('customer/session')->isLoggedIn() )
					{
		                $event = 'login';
					}
					else
					{
		                $event = 'login_failed';
					}
	                $html_result[] = $this->trackEvent($event, array('username' => $username));
	            }
				/***
				* Logout
				*/
	            elseif( $action == 'customer_account_logoutSuccess' && self::getConfig('advanced/login_tracking') )
	            {
	                $html_result[] = $this->trackEvent('logout');
	            }
				/***
				* Forgot password post
				*/
	            elseif( $action == 'customer_account_forgotpasswordpost' && self::getConfig('advanced/forgot_password_tracking') )
	            {
	                $html_result[] = $this->trackEvent('forgot password', array("email" => $request['email']));
	            }
				/***
				* Reset password post
				*/
	            elseif( $action == 'customer_account_resetpasswordpost' && self::getConfig('advanced/forgot_password_tracking') )
	            {
	                $html_result[] = $this->trackEvent('reset_password');
	            }
				/***
				* Change password
				*/
	            elseif( $action == 'customer_account_editPost' && self::getConfig('advanced/account_actions_tracking') )
	            {
	            	if( $request['change_password'] && $request['password'] != $request['current_password'] && $request['password'] == $request['confirmation'] )
	            	{
		            	$html_result[] = $this->trackEvent('change_password');
		            }
	            }
				/***
				* New and update address
				*/
	            elseif( $action == 'customer_address_formPost' && self::getConfig('advanced/account_actions_tracking') )
	            {
	            	$html_result[] = $this->trackEvent('address_action');
	            }
				/***
				* 404
				*/
	            elseif( $action == 'cms_index_noRoute' && self::getConfig('advanced/no_route_tracking') )
	            {
	                $html_result[] = $this->trackEvent('404', array(
	                	'url' => Mage::helper('core/url')->getCurrentUrl(),
	                	'referer' => Mage::helper('core/http')->getHttpReferer(),
	                ));
	            }
				/***
				* Search
				*/
	            elseif( $action == 'catalogsearch_result_index' && self::getConfig('advanced/search_tracking') )
	            {
	                $html_result[] = $this->trackEvent('search', array(
	                	'query' => $request['q'],
	                ));
	            }
				/***
				* Search avanced
				*/
	            elseif( $action == 'catalogsearch_advanced_result' && self::getConfig('advanced/search_tracking') )
	            {
					$advanced_search = Mage::getModel("catalogsearch/advanced")->addFilters($request);
				
					if($advanced_search && $search_criterias = $advanced_search->getSearchCriterias())
					{
						$criterias = array();
						foreach($search_criterias as $criteria)
						{
							$criterias[$criteria['name']] = $criteria['value'];
						}
		                $html_result[] = $this->trackEvent('search_advanced', array(
		                	'criterias' => $criterias,
		                ));
					}
	            }
				/***
				* Contact form post
				*/
	            elseif( $action == 'contacts_index_post' && self::getConfig('advanced/contact_form_tracking') )
	            {
	                $html_result[] = $this->trackEvent('contact_form', array(
	                	'name' => $request['name'],
	                	'email' => $request['email'],
	                	'phone' => $request['telephone'],
	                	'comment' => $request['comment'],
	                ));
	            }
				/***
				* Newsletter actions
				*/
	            elseif( $action == 'newsletterSubscriberSaveAfter' && self::getConfig('advanced/newsletter_tracking') && $trigger['status_change'] == true )
	            {
					$event = ($request['subscriber_status'] == Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) ? 'unsubscribe' : 'subscribe';
					
	                $html_result[] = $this->trackEvent('newsletter_' . $event, array(
	                	'email' => $request['subscriber_email'],
	                ));
	            }
				/***
				* product review post
				*/
	            elseif( $action == 'review_product_post' && self::getConfig('advanced/product_review_tracking') )
	            {
					$event_ratings = array();
					
	                foreach ($request['ratings'] as $ratingId => $optionId)
					{
	                    $rating = Mage::getModel('rating/rating')->load($ratingId);
	                    $rating_option = Mage::getModel('rating/rating_option')->load($optionId);
						$event_ratings[$rating->getRatingCode()] = $rating_option->getValue() . "/5";
	                }
					
	                $html_result[] = $this->trackEvent('product_review', array(
	                	'nickname' => $request['nickname'],
	                	'title' => $request['title'],
	                	'comment' => $request['detail'],
						'ratings' => $event_ratings
	                ));
	            }
				
			}
			
			return implode("\n", $html_result);
        }
    }
    
    /**
     * Returns html for block to track given event
     *
     * @param string $event
     * @param array $properties
     * @return string
     */
    protected function trackEvent($event, $properties = array())
    {
        $html = "stetic.track('{$event}'";
        if(is_array($properties) && !empty($properties))
        {
            $html .= ", " . json_encode($properties);
        }
        $html .= ");";
        return $html . PHP_EOL;
    }
	
    /**
     * Get event properties for a product id
     *
     * @param string $product_id
     * @return mixed
     */
	protected function get_product_from_request($product_id, $request = false)
	{
        if($product_object = Mage::getModel('catalog/product')->load($product_id))
        {
            $product = $product_object->getData();			
            $categories = array();                
            $category_ids = $product_object->getCategoryIds();
            foreach($category_ids as $category_id)
            {
                $category = Mage::getModel('catalog/category')->load($category_id) ;
                $categories[] = $category->getName();
            }
			
			$result_product = array(
                "id" => $product_id,
                "name" => $product['name'],
                "sku" => $product['sku'],
                "price" => (float)$this->get_product_price($product_object, $request),
                "category" => $categories
            );
        
            return $result_product;
        }
		
		return false;
	}
	
	
    /**
     * Returns product price for various product types
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $request
     * @return string
     */
	public function get_product_price($product, $request = false)
	{
		if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $request && $request['bundle_option'])
		{
			$selectionCollection = $product->getTypeInstance(true)->getSelectionsCollection(
				array_keys($request['bundle_option']), $product
			);

			$price = 0;
			$bundled_items = array();
			foreach($selectionCollection as $option) 
			{
				if( isset($request['bundle_option'][$option->getOptionId()]) && $request['bundle_option'][$option->getOptionId()] == $option->getSelectionId() )
				{
					$qty = $request['bundle_option_qty'][$option->getOptionId()];
					if(!$qty)
					{
						$qty = 1;
					}
					$price += $option->getPrice()*1;
					$bundled_items[] = array($option->product_id, $option->getOptionId(), $option->getSelectionId(), $option->getPrice());
				}
			}			
			return $price;
			
	    }
		elseif($request['qty'] && (int)$request['qty'] > 1)
		{
			return $product->getTierPrice((int)$request['qty']);
		}
	    elseif($product->getFinalPrice())
		{
	        return $product->getFinalPrice();
	    }
		else
		{
	        return 0.00;
	    }
	}
	
    /**
     * Get configuration value
     *
     * @param string $key
     * @return string
     */
	protected static function getConfig($key)
	{
		return Mage::helper('Stetic')->getConfig($key);
	}
	
    /**
     * Log Helper
	 * Logs a message to var/log in foursatts namespace
     *
     * @param string $msg
     */
	protected static function log()
	{
		$messages = func_get_args();
		Mage::log($messages, null, 'stetic.log');
	}
}
