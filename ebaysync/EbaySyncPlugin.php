<?php

namespace Craft;

class EbaySyncPlugin extends BasePlugin
{
    public function getName()
    {
         return Craft::t('Ebay Sync');
    }
    public function getDescription()
    {
        return Craft::t('This should pull back data from the an ebay account, using the Ebay API `GetSellerList` call');
    }
    public function getDocumentationUrl()
    {
        return 'https://github.com/lexbi/ebaysync/blob/master/README.md';
    }
    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/lexbi/ebaysync/master/releases.json';
    }

    public function getVersion()
    {
        return '0.1';
    }

    public function getSchemaVersion()
    {
        return '0.1';
    }
    public function getDeveloper()
    {
        return 'Joe Pagan';
    }
    public function getDeveloperUrl()
    {
        return 'joe-pagan.com';
    }
    public function hasCpSection()
    {
        return false;
    }
    protected function defineSettings()
    {
        return array(
            'ebayUser' => array(AttributeType::String, 'label' => 'Ebay User', 'default' => ''),
            'ebayEnvironment' => array(AttributeType::String, 'label' => 'ebayEnvironment', 'default' => 'Production'),
            'ebayCallType' => array(AttributeType::String, 'label' => 'ebayCallType', 'default' => 'GetSellerList'),

            'ebayProductionDevId' => array(AttributeType::String, 'label' => 'ebayProductionDevId', 'default' => ''),
            'ebayProductionAppId' => array(AttributeType::String, 'label' => 'ebayProductionAppId', 'default' => ''),
            'ebayProductionCertId' => array(AttributeType::String, 'label' => 'ebayProductionCertId', 'default' => ''),
            'ebayProductionUserToken' => array(AttributeType::String, 'label' => 'ebayProductionUserToken', 'default' => ''),

            'ebaySandboxDevId' => array(AttributeType::String, 'label' => 'ebaySandboxDevId', 'default' => ''),
            'ebaySandboxAppId' => array(AttributeType::String, 'label' => 'ebaySandboxAppId', 'default' => ''),
            'ebaySandboxCertId' => array(AttributeType::String, 'label' => 'ebaySandboxCertId', 'default' => ''),
            'ebaySandboxUserToken' => array(AttributeType::String, 'label' => 'ebaySandboxUserToken', 'default' => ''),

        );
    }
    public function getSettingsHtml()
    {
       return craft()->templates->render('ebaysync/EbaySync_Settings', array(
           'settings' => $this->getSettings()
       ));
    }
    public function prepSettings($settings)
    {
        return $settings;
    }
}
