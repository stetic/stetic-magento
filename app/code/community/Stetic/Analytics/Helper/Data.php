<?php

/**
 * Stetic Analytics Magento Extension
 *
 * @category    Stetic
 * @package     Stetic_Analytics
 * @copyright   Copyright (c) 2014 Stetic (http://www.stetic.com/)
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

class Stetic_Analytics_Helper_Data extends Mage_Core_Helper_Data
{
    /**
     * Config XML path prefix
     * @var string
     */
    const XML_PATH_PREFIX       = 'stetic_analytics/';
    
    
    public function isEnabled($store = null)
    {
        /**
         * Enabled the extension if site token is available and the
         * extention is enabled in settings.
         */
        $enabled = $this->getConfig('settings/enabled', $store);
        $site_token = $this->getSiteToken($store);
        return $enabled && $site_token;
    }
    
    public function getSiteToken($store = null)
    {
         return $this->getConfig('settings/site_token', $store);
    }
    
    public function getCookieDomain($store = null)
    {
         return $this->getConfig('settings/cookie_domain', $store);
    }
    
    /**
     * Get store config from Mage
     *
     * @param   string $$key
     * @param   string $store
     * @return  mixed
     */
    public function getConfig($key, $store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_PREFIX . $key, $store);
    }
}