<?php

namespace Craft;

class EbaySync_EbaySyncController extends BaseController
{

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     * @access protected
     */
    protected $allowAnonymous = array('actionSyncAllEbayEntries');

    /**
     * Handle a request going to our plugin's index action URL, e.g.: actions/ebaySync
     */
    public function actionSyncAllEbayEntries()
    {
        $getList = new EbaySyncService();
        $getList->callEbay();
        $getList->createOrUpdateEbayEntries();
        EbaySyncPlugin::log("All data is now synchronised", LogLevel::Error);
        craft()->userSession->setNotice(Craft::t('All data is now synchronised, please check your entries'));
    }
}
