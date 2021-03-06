<?php

use Shopware\SitionSooqr\Components\Log;
use Shopware\SitionSooqr\Components\SooqrXml;
use Shopware\SitionSooqr\Components\Helpers;
use Shopware\SitionSooqr\Components\ShopwareConfig;

class Shopware_Controllers_Frontend_SitionSooqr extends Enlight_Controller_Action
{
	public function xmlAction($args) 
	{
		$request = $this->Request();

		$force = $request->get('force');
		$force = empty($force) ? false : true;

		$shopId = intval($request->get('shop'));

		$sooqr = new SooqrXml($shopId);

		$sooqr->outputXml($force);

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
		
		$currency = $currentShop->getCurrency();
		$locale = $currentShop->getLocale();

        $config = new ShopwareConfig(Shopware()->Config());

		$searchEnabled = in_array(trim($config->get('add_client_side_script')), [ "yes", "1", "true", "ja" ]);

		$arr = [
			"search" => [
				"enabled" => $searchEnabled ? "1" : "0"
			],
			"feeds" => [
				// "name" => $currentShop->getName(),
				// "feed_url" => "http://" . Helpers::pathCombine( $host, $currentShop->getBaseUrl(), 'frontend', 'sition_sooqr', 'xml' ),
				// "currency" => $currency ? $currency->getCurrency() : "",
				// "locale" => $locale ? $locale->getLocale() : "",
				// "country" => "NL",
				// "timezone" => timezone_name_get(date_timezone_get(date_create(null))),
				// "system" => date_default_timezone_get(),
				// "extension" => "Sition_SitionSooqr",
				// "extension_version" => $this->getPluginVersion()
			]
		];

		$arr["feeds"] = array_map(function($shop) use ($host) {

			$currency = $shop->getCurrency();
			$locale = $shop->getLocale();

			return [
				"name" => $shop->getName(),
				"feed_url" => "http://" . Helpers::pathCombine( $host, $shop->getBaseUrl(), 'frontend', 'sition_sooqr', 'xml' ) . "?shop={$shop->getId()}",
				"currency" => $currency ? $currency->getCurrency() : "",
				"locale" => $locale ? $locale->getLocale() : "",
				"country" => "NL",
				"timezone" => timezone_name_get(date_timezone_get(date_create(null))),
				"system" => date_default_timezone_get(),
				"extension" => "Sition_SitionSooqr",
				"extension_version" => $this->getPluginVersion()
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
