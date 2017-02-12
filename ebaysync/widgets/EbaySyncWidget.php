<?php
namespace Craft;

class EbaySyncWidget extends BaseWidget
{

	protected $colspan = 1;

	public function getName()
	{
		return Craft::t('Synchronise Ebay account products with database');
	}

	public function getBodyHtml()
	{
	    return craft()->templates->render('ebaysync/_ebaysyncwidgettemplate');
	}
}
