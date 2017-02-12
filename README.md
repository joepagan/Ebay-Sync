# Ebay Sync plugin for Craft CMS

This should pull back data from the Ebay API, specifically [GetSellerList](http://developer.ebay.com/devzone/xml/docs/reference/ebay/GetSellerList.html)

## Installation

To install Ebay Sync, follow these steps:

1. Download & unzip the file and place the `ebaysync` directory into your `craft/plugins` directory
2. -OR- do a `git clone https://github.com/lexbi/ebaysync.git` directly into your `craft/plugins` folder. You can then update it with `git pull`
3. Install plugin in the Craft Control Panel under Settings > Plugins
4. The plugin folder should be named `ebaysync` for Craft to see it. GitHub recently started appending `-master` (the branch name) to the name of the folder for zip file downloads.

Ebay Sync works on Craft 2.4.x and Craft 2.5.x.

## Ebay Sync Overview

Gets an Ebay account's products that they are selling, adds them to CraftCMS as entries.

## Configuring Ebay Sync

Add your API keys to the backend area, though change the page it syncs to in the `services/EbaySyncService.php`

## Using Ebay Sync

Either use the sync button on the dashboard widget, or, setup a cron to hit actions/ebaySync/EbaySync/SyncAllEbayEntries to force the CMS to sync.

Does so will get all the ebay products, check they exist, if they do update the entry to reflect new content. Add if the entries don't exist, create them!.
