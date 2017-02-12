<?php

namespace Craft;

class EbaySyncService extends BaseApplicationComponent
{

     private function getMySetting($value)
    {
        $plugin = craft()->plugins->getPlugin('ebaySync');
        $settings = $plugin->getSettings();

        return $settings->$value;
    }

    private $_siteId = 3;  // default: uk
    private $_environment;  // toggle between sandbox and production
    private $_eBayApiVersion = 963;
    private $_call;
    private $_ebayUser;
    private $_keys;

    // Called within function 1
    private function _getRequestBody()
    {
         $apiValues = $this->_keys[$this->_environment];

         $dateNow = new DateTime();
         $dateNow->format('c');
         $date4MonthsAgo = new DateTime();
         $date4MonthsAgo->modify('-120 days');
         $date4MonthsAgo->format('c');

        ///Build the request Xml string
        $requestXmlBody = '<?xml version="1.0" encoding="utf-8" ?>';
        $requestXmlBody .= '<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
        $requestXmlBody .= '<RequesterCredentials>';
        $requestXmlBody .=      '<eBayAuthToken>'.$this->_keys[$this->_environment]['UserToken'].'</eBayAuthToken>';
        $requestXmlBody .= '</RequesterCredentials>';
        $requestXmlBody .= '<Pagination ComplexType="PaginationType">';
        $requestXmlBody .=      '<EntriesPerPage>30</EntriesPerPage>';
        $requestXmlBody .=      '<PageNumber>1</PageNumber>';
        $requestXmlBody .= '</Pagination>';
        $requestXmlBody .= '<UserID>'.$this->_ebayUser.'</UserID>';
        $requestXmlBody .= '<StartTimeFrom>';
        $requestXmlBody .= $date4MonthsAgo;
        $requestXmlBody .= '</StartTimeFrom>';
        $requestXmlBody .= '<StartTimeTo>'.$dateNow.'</StartTimeTo>';
        $requestXmlBody .= '<IncludeWatchCount>true</IncludeWatchCount>';
        $requestXmlBody .= '<GranularityLevel>Medium</GranularityLevel>';//could change if its slow
        //$requestXmlBody .= '<DetailLevel>Medium</DetailLevel>';//could change if its slow
        //$requestXmlBody .= '<OutputSelector>Item.ConditionDescription</OutputSelector>';
        $requestXmlBody .= '<ErrorLanguage>en_GB</ErrorLanguage>';
        $requestXmlBody .= '</GetSellerListRequest>​';

        return $requestXmlBody;
    }

    // First function that is called
    public function callEbay()
    {
        // Setting the priv vars in the pub function
        $this->_environment = $this->getMySetting("ebayEnvironment");
        $this->_keys = array(
            'production' => array(
                'DEVID'     => $this->getMySetting('ebayProductionDevId'),
                'AppID'     => $this->getMySetting('ebayProductionAppId'),
                'CertID'    => $this->getMySetting('ebayProductionCertId'),
                'UserToken' => $this->getMySetting('ebayProductionUserToken'),
                'ServerUrl' => 'https://api.ebay.com/ws/api.dll'
                ),
            'sandbox' => array(
                'DEVID'     => $this->getMySetting('ebaySandboxDevId'),
                'AppID'     => $this->getMySetting('ebaySandboxAppId'),
                'CertID'    => $this->getMySetting('ebaySandboxCertId'),
                'UserToken' => $this->getMySetting('ebaySandboxUserToken'),
                'ServerUrl' => 'https://api.sandbox.ebay.com/ws/api.dll'
            )
        );
        $this->_call = $this->getMySetting('ebayCallType');
        $this->_ebayUser = $this->getMySetting('ebayUser');
        $apiValues = $this->_keys[$this->_environment];

        $connection = curl_init();
        curl_setopt($connection, CURLOPT_URL, $apiValues['ServerUrl']);
        curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);

        $headers = array (
            'X-EBAY-API-COMPATIBILITY-LEVEL: ' . $this->_eBayApiVersion,
            'X-EBAY-API-DEV-NAME: ' . $apiValues['DEVID'],
            'X-EBAY-API-APP-NAME: ' . $apiValues['AppID'],
            'X-EBAY-API-CERT-NAME: ' . $apiValues['CertID'],
            'X-EBAY-API-CALL-NAME: ' . $this->_call,
            'X-EBAY-API-SITEID: ' . $this->_siteId,
        );

        curl_setopt($connection, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($connection, CURLOPT_POST, 1);

        $requestBody = $this->_getRequestBody();

        curl_setopt($connection, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
        $responseXml = curl_exec($connection);
        curl_close($connection);

        return $responseXml;

    }

    // Second function called
    public function createOrUpdateEbayEntries()
    {

        //Xml string is parsed and creates a DOM Document object
        $responseDoc = new \DomDocument();
        $responseDoc->loadXML($this->callEbay());

        //get any error nodes
        $errors = $responseDoc->getElementsByTagName('Errors');

        //if there are error nodes
        if($errors->length > 0)
        {
            echo '<P><B>eBay returned the following error(s):</B>';
            //display each error
            //Get error code, ShortMesaage and LongMessage
            $code     = $errors->item(0)->getElementsByTagName('ErrorCode');
            $shortMsg = $errors->item(0)->getElementsByTagName('ShortMessage');
            $longMsg  = $errors->item(0)->getElementsByTagName('LongMessage');
            //Display code and shortmessage
            echo '<P>', $code->item(0)->nodeValue, ' : ', str_replace(">", "&gt;", str_replace("<", "&lt;", $shortMsg->item(0)->nodeValue));
            //if there is a long message (ie ErrorLevel=1), display it
            if(count($longMsg) > 0) {
                echo '<BR>', str_replace(">", "&gt;", str_replace("<", "&lt;", $longMsg->item(0)->nodeValue));
            }

        } else { //no errors
            //get results nodes
            $responses = $responseDoc->getElementsByTagName("GetSellerListResponse");
            if($responses){
                foreach ($responses as $response) {

                    $acks = $response->getElementsByTagName("Ack");
                    $ack   = $acks->item(0)->nodeValue;

                    $totalNumberOfEntries  = $response->getElementsByTagName("TotalNumberOfEntries");
                    $totalNumberOfEntries  = $totalNumberOfEntries->item(0)->nodeValue;

                    $carsForSaleTopLevelPage = craft()->elements->getCriteria(ElementType::Entry);
                    $carsForSaleTopLevelPage->id = 93;
                    $carsForSaleTopLevelPage = $carsForSaleTopLevelPage->first();

                    // loop of existing ebay ids
                    $existingCarEntries = craft()->elements->getCriteria(ElementType::Entry);
                    $existingCarEntries->section = "generalPagesSection";
                    $existingCarEntries->type = "carsForSaleDetail";
                    $existingCarEntries->status = "null";
                    $existingCarEntries->find();

                    $existingCarsWithEbayIds = array();
                    $existingEbayIds = array();

                    foreach($existingCarEntries as $car){
                        if(!empty($car->getContent()->ebayItemId)){
                            // little cheat, set 2 arrays to be created so I can use in_array, and avoid multidimensional arrays becuase both are necessary
                            $existingCarsWithEbayIds[] = $car->id;
                            $existingEbayIds[] = $car->getContent()->ebayItemId;
                        }
                    }

                    $items = $response->getElementsByTagName("Item");

                    // loop of the data back from ebay (this loop is per ebay item)
                    for($i=0; $i<$totalNumberOfEntries; $i++) {

                        $itemId = $items->item($i)->getElementsByTagName('ItemID')->item(0)->nodeValue;
                        $itemUrl = $items->item($i)->getElementsByTagName('ViewItemURL')->item(0)->nodeValue;
                        $startTime = $items->item($i)->getElementsByTagName('StartTime')->item(0)->nodeValue;
                        $endTime = $items->item($i)->getElementsByTagName('EndTime')->item(0)->nodeValue;
                        $bidCount = $items->item($i)->getElementsByTagName('BidCount')->item(0)->nodeValue;
                        $priceInGBP = $items->item($i)->getElementsByTagName('ConvertedCurrentPrice')->item(0)->nodeValue;
                        $status = $items->item($i)->getElementsByTagName('ListingStatus')->item(0)->nodeValue;
                        $title = $items->item($i)->getElementsByTagName('Title')->item(0)->nodeValue;
                        $image = $items->item($i)->getElementsByTagName('PictureDetails')->item(0)->nodeValue;
                        //$description = $items->item($i)->getElementsByTagName('Description')->item(0)->nodeValue; - not available unless "return all" is used
                        $output_array = ""; // setting black var because it seems to carry over each loop

                        preg_match("/(?=PicturePack)(.*)$/", $image, $output_array);

                        // this if is to stop ebay items with no images getting here
                        if(!empty($output_array[0])){
                            $output_array = $output_array[0];
                            $stringOfImageUrls = str_replace("PicturePack", "", $output_array, $count);
                            $stringOfImageUrls = str_replace("http", "|http", $stringOfImageUrls, $count);
                            $stringOfImageUrls = str_replace("\$_1.JPG", "\$_57.JPG", $stringOfImageUrls, $count);

                            $imageUrls = explode("|", $stringOfImageUrls);
                        }

                        // Creates completely new pages
                        if(!in_array($itemId, $existingEbayIds)){

                            // New page
                            $entry = new EntryModel();
                            $entry->sectionId = 3;
                            $entry->typeId    = 6;
                            $entry->authorId  = 1;
                            $entry->parentId  = 93;
                            $entry->enabled   = true;

                            $entry->setContent([
                                "title" => "Ebay Item - " . $title,
                                "pageTitle" => $title,
                                "ebayItemId" => $itemId,
                                "ebayItemPrice" => $priceInGBP,
                                "ebayItemStatus" => $status,
                                "ebayItemUrl" => $itemUrl,
                                "ebayItemStartTime" => $startTime,
                                "ebayItemEndTime" => $endTime
                            ]);

                            $entry->expiryDate = $endTime;

                            // Dealing with images
                            $craftImageAssetArray = [];
                            $imageUrlCount=0;
                            $iAssetSourceId = 5; // Ebay Images asset source
                            $iAssetFolderId = craft()->assets->getRootFolderBySourceId($iAssetSourceId)->id;

                            // this if is to stop ebay items with no images getting here
                            if(!empty($imageUrls)){
                                foreach($imageUrls as $imageUrl){

                                    if(!empty($imageUrl)){

                                        // var for url is now $pureImageUrlString
                                        preg_match("/.+?(?=\?)/",$imageUrl, $pureImageUrlString);

                                        $sFilename = strtolower($title); // lowercase the title
                                        $sFilename = preg_replace('/\s/', '_', $sFilename); // replace spaces with underscore
                                        $sFilename = preg_replace('/[^A-Za-z0-9\-\_]/', '', $sFilename); // only allow ez chars
                                        $sFilename = $sFilename."_".$imageUrlCount.".jpg"; // turn it into the final filename adding a number on end

                                        // If filename already exists, add it to the $craftImageAssetArray (which later adds to the entry assets field )
                                        $criteria = craft()->elements->getCriteria(ElementType::Asset);
                                        $criteria->filename = $sFilename;
                                        $existingFile = craft()->assets->findFile($criteria);

                                        if(!empty($existingFile)) {
                                            $craftImageAssetArray[] = $existingFile->id;
                                            EbaySyncPlugin::log("Pre-existing image ".$sFilename. " applied to new entry: ".$entry->title, LogLevel::Error);

                                        } else {

                                            $sFileContents = file_get_contents($pureImageUrlString[0]);
                                            $sTempFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $sFilename;
                                            file_put_contents($sTempFilePath, $sFileContents);

                                            // Get craft to copy the temp file into the assets folder
                                            $oAssetOperationResponse = craft()->assets->insertFileByLocalPath(
                                                $sTempFilePath,
                                                $sFilename,
                                                $iAssetFolderId,
                                                AssetConflictResolution::Replace
                                            );

                                            $craftImageAssetArray[] = $oAssetOperationResponse->responseData["fileId"];
                                            EbaySyncPlugin::log("New image ".$sFilename. " applied to new entry: ".$entry->title, LogLevel::Error);

                                        }

                                    }

                                    $imageUrlCount++;
                                }
                            }

                            $entry->setContentFromPost([
                                "ebayItemImages" => $craftImageAssetArray
                            ]);

                            $success = craft()->entries->saveEntry($entry);
                            craft()->userSession->setNotice(Craft::t('New entry added '.$entry->title.'.'));

                        } else {

                            // This gets the CORRECT entry_id, we know this because it was set at the same time as the other array which checks for entryEbayItemIds
                            $existingEbayIdsKey = array_search($itemId, $existingEbayIds);

                            // Page Exists already so update the data from ebay yo!
                            $existingEntry = craft()->elements->getCriteria(ElementType::Entry);
                            $existingCarEntries->section = "generalPagesSection";
                            $existingCarEntries->type = "carsForSaleDetail";
                            $existingCarEntries->find();

                            foreach($existingCarEntries as $existingCarEntry){

                                if($existingCarEntry->id == $existingCarsWithEbayIds[$existingEbayIdsKey]){

                                    if($existingCarEntry->getContent()->ebayKeepItemDataSynchronised === "yes"){

                                        $existingCarEntry->setContentFromPost([
                                            "title" => "Ebay Item - " . $title,
                                            "pageTitle" => $title,
                                            "ebayItemId" => $itemId,
                                            "ebayItemPrice" => $priceInGBP,
                                            "ebayItemStatus" => $status,
                                            "ebayItemUrl" => $itemUrl,
                                            "ebayItemStartTime" => $startTime,
                                            "ebayItemEndTime" => $endTime
                                        ]);

                                        $existingCarEntry->expiryDate = $endTime;

                                        // Get existing entry assets
                                        $existingCarEntryAssets = $existingCarEntry->ebayItemImages;

                                        // Dealing with images
                                        $craftImageAssetArray = [];
                                        $existingCarEntryAssetFilesnames = [];
                                        $imageUrlCount=0;
                                        $iAssetSourceId = 5; // Ebay Images asset source
                                        $iAssetFolderId = craft()->assets->getRootFolderBySourceId($iAssetSourceId)->id;

                                        // loop all image urls from ebay
                                        foreach($imageUrls as $imageUrl){

                                            //if item is not empty
                                            if(!empty($imageUrl)){

                                                $pureImageUrlString = ""; // black var because its possible carried over in loop
                                                // var for url is now $pureImageUrlString
                                                preg_match("/.+?(?=\?)/",$imageUrl, $pureImageUrlString);

                                                $sFilename = "";
                                                $sFilename = strtolower($title); // lowercase the title
                                                $sFilename = preg_replace('/\s/', '_', $sFilename); // replace spaces with underscore
                                                $sFilename = preg_replace('/[^A-Za-z0-9\-\_]/', '', $sFilename); // only allow ez chars
                                                $sFilename = $sFilename."_".$imageUrlCount.".jpg"; // turn it into the final filename adding a number on end

                                                // If filename already exists, add it to the $craftImageAssetArray (which later adds to the entry assets field )
                                                if(!empty($existingCarEntryAssets)) {

                                                    // if an entry image exists already add it's id to the array (if a new image is pulled down, all current ids still need to be added)
                                                    foreach($existingCarEntryAssets as $existingCarEntryAsset){
                                                        $craftImageAssetArray[] = $existingCarEntryAsset->id;
                                                        // create an array of filenames to search on next
                                                        $existingCarEntryAssetFilesnames[] = $existingCarEntryAsset->filename;
                                                    }

                                                    // if new image is not assign to entry yet
                                                    if(!array_search($sFilename, $existingCarEntryAssetFilesnames)){
                                                        $sFileContents = file_get_contents($pureImageUrlString[0]);
                                                        $sTempFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $sFilename;
                                                        file_put_contents($sTempFilePath, $sFileContents);

                                                        // Get craft to copy the temp file into the assets folder
                                                        $oAssetOperationResponse = craft()->assets->insertFileByLocalPath(
                                                            $sTempFilePath,
                                                            $sFilename,
                                                            $iAssetFolderId,
                                                            AssetConflictResolution::Replace
                                                        );

                                                        $craftImageAssetArray[] = $oAssetOperationResponse->responseData["fileId"];
                                                        EbaySyncPlugin::log('New image '.$sFilename. ' with other pre-existing assets applied to existing entry: '.$existingCarEntry->title);
                                                    }

                                    			} else {

                                                    $sFileContents = file_get_contents($pureImageUrlString[0]);
                                                    $sTempFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $sFilename;
                                                    file_put_contents($sTempFilePath, $sFileContents);

                                                    // Get craft to copy the temp file into the assets folder
                                                    $oAssetOperationResponse = craft()->assets->insertFileByLocalPath(
                                                        $sTempFilePath,
                                                        $sFilename,
                                                        $iAssetFolderId,
                                                        AssetConflictResolution::Replace
                                                    );

                                                    $craftImageAssetArray[] = $oAssetOperationResponse->responseData["fileId"];
                                                    EbaySyncPlugin::log('New image '.$sFilename. ' applied to existing entry: '.$existingCarEntry->title);

                                                }

                                            }

                                            $imageUrlCount++;
                                        }

                                        $existingCarEntry->setContentFromPost([
                                            "ebayItemImages" => $craftImageAssetArray
                                        ]);

                                        // If item is completed or ended
                                        if($status == "Completed" || $status == "Ended"){

                                            if(isset($endTime)){
                                                // Create a date 1 month after end time
                                                $MonthAfterEndTime = new DateTime($endTime);
                                                $MonthAfterEndTime->modify('+1 month');

                                                // Disable entry if 1 month has passed since completed or ended.
                                                if($MonthAfterEndTime < new DateTime()){
                                                    $existingCarEntry->enabled = false;
                                                } else {
                                                    $existingCarEntry->enabled = true;
                                                }

                                            }

                                        }

                                        $success = craft()->entries->saveEntry($existingCarEntry);
                                        EbaySyncPlugin::log('Existing entry updated '.$existingCarEntry->title);

                                    }

                                }

                            }

                        }

                        if (!isset($success))
                        {
                            if(isset($entry)){
                                EbaySyncPlugin::log('Couldn’t save an entry "'.$entry->title.'"', LogLevel::Error);
                            } else if(isset($existingCarEntry->title)){
                                EbaySyncPlugin::log('Couldn’t update an entry "'.$existingCarEntry->title.'"', LogLevel::Error);
                            }
                            EbaySyncPlugin::log("No idea what just went wrong SORRY MEIGHT", LogLevel::Error);

                        }

                    }
                }
            } else{
               return "No Ebay Listings are available at the current time.";
           }

       }

   } // end of pub function

}
