<?php

use Shopware\SitionSooqr\Components\Log;
use Shopware\SitionSooqr\Components\SooqrXml;

class Shopware_Controllers_Frontend_SitionSooqr extends Enlight_Controller_Action
{
	public function xmlAction() 
	{	
		//Shopware()->Debuglogger()->info('some message from sition sooqr');
		
		$sooqr = new SooqrXml;

		$sooqr->outputXml();
		
// 		$iter = new RecursiveIteratorIterator(
// 		    new RecursiveDirectoryIterator(__DIR__ . "/../../../../../../../../media/image", RecursiveDirectoryIterator::SKIP_DOTS),
// 		    RecursiveIteratorIterator::SELF_FIRST,
// 		    RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
// 		);

// 		echo "iter";
// $paths = array($root);
// foreach ($iter as $path => $dir) {
//     if ($dir->isDir()) {
//         $paths[] = $path;
//     }
// }

// print_r($paths);

		// pr(inst(Shopware()->Config()));preg_quote("Ort-ok17p1-1")
		// Shopware()->Config()->bla();

		// echo Shopware()->Config()->get("host");

		// pr(Shopware()->Modules()->Articles()->sGetComparisons());

		// $sitemap = Shopware()->Models()->getRepository('sitemapxml.repository');

		// pr(inst($sitemap));

		// exit request, don't render a view
		exit();
	}
}