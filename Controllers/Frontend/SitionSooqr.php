<?php

use Shopware\SitionSooqr\Components\Log;
use Shopware\SitionSooqr\Components\SooqrXml;
use Shopware\SitionSooqr\Components\Helpers;

class Shopware_Controllers_Frontend_SitionSooqr extends Enlight_Controller_Action
{
	public function xmlAction() 
	{
		$sooqr = new SooqrXml;

		$sooqr->outputXml();

		// exit request, don't render a view
		exit();
	}

	public function installationAction()
	{
		$entityManager = Shopware()->Models();
		$config = Shopware()->Config();

		$currentShop = Shopware()->Shop();
		$mainShop = $currentShop->getMain();

		$shopRepository = $this->getShopRepository();
		$shops = $shopRepository->findAll();

		$host = $config->get("host");
		
		$arr = [
			"search" => [
				"enabled" => "0"
			],
			"feeds" => []
		];

		$arr["feeds"] = array_map(function($shop) use ($host) {

			$currency = $shop->getCurrency();
			$locale = $shop->getLocale();

			return [
				"name" => $shop->getName(),
				"feed_url" => "http://" . Helpers::pathCombine( $host, $shop->getBaseUrl(), 'frontend', 'sition_sooqr', 'xml' ),
				"currency" => $currency ? $currency->getCurrency() : "",
				"locale" => $locale ? $locale->getLocale() : "",
				"country" => "NL",
				"timezone" => timezone_name_get(date_timezone_get(date_create(null))),
				"system" => date_default_timezone_get(),
				"extension" => "Sition_SitionSooqr",
				"version" => $this->getPluginVersion()
			];

		}, $shops);

		header("Content-Type:application/json");
		echo json_encode($arr, JSON_PRETTY_PRINT);

		exit();
	}

	protected function getShopRepository()
	{
		$entityManager = Shopware()->Models();
		return $entityManager->getRepository('Shopware\Models\Shop\Shop');
	}

	protected function getPluginJson()
	{
		return json_decode(file_get_contents(__DIR__ . '/../../plugin.json'), true);
	}

	/**
	 * Get plugin version from plugin.json
	 * @return string    Plugin version string
	 */
    protected function getPluginVersion()
    {
        $info = $this->getPluginJson();
        
        if( $info )
        {
        	if( isset($info['currentVersion']) ) return $info['currentVersion'];
        }

        return false;
    }
}