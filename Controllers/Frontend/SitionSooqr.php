<?php

use Shopware\SitionSooqr\Components\Log;
use Shopware\SitionSooqr\Components\SooqrXml;

class Shopware_Controllers_Frontend_SitionSooqr extends Enlight_Controller_Action
{
	public function xmlAction() 
	{
		$compress = $this->Request()->getParam('compress', 1);
		$extension = $this->Request()->getParam('extension', 'xml');

		$sooqr = new SooqrXml;

		$sooqr->outputXml();
		
		// exit request, don't render a view
		exit();
	}
}