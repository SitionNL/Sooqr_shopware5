<?php

namespace Shopware\SitionSooqr\Components;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Common\Collections\ArrayCollection;
use Shopware\SitionSooqr\Components\SimpleXMLElementExtended as SimpleXMLElement;
use Shopware\SitionSooqr\Components\Locking;
use Shopware\SitionSooqr\Components\Gzip;
use Shopware\SitionSooqr\Components\ShopwareConfig;
use Shopware\SitionSooqr\Components\Helpers;
use Shopware\SitionSooqr\Components\PluginJson;
use Shopware\SitionSooqr\CategoryTree\CategoryTree;
use Shopware\SitionSooqr\CategoryTree\CategoryTreeEntry;

class SooqrXml
{
	/**
	 * @var ModelManager
	 */
	protected $em;

	/**
	 * @var Shopware\SitionSooqr\Components\Locking
	 */
	protected $lock;

	/**
	 * @var  Shopware\Models\Shop\DetachedShop
	 */
	protected $shop;

	/**
	 * @var  Shopware_Components_Config
	 */
	protected $config;

	/**
	 * @var Shopware Database Instance
	 */
	protected $db;

	/**
	 * @var Shopware\SitionSooqr\Components\PluginJson
	 */
	protected $pluginJson;

	/**
	 * Array to cache some variables
	 * @var array
	 */
	protected $cache = [
		'config' => []
	];

	public function __construct($shopId = null)
	{
		set_time_limit(60 * 60); // 1 hour

		$this->em = Shopware()->Models(); // modelManager

		$this->lock = new Locking( $this->getLockFile() );

		$this->setShop($shopId);
		$this->config = Shopware()->Config();
		$this->db = Shopware()->Db();
		$this->pluginJson = new PluginJson;

		// add namespaces, so they can be used in the generation of xml snippets
		SimpleXMLElement::addNamespace('sqr', "http://base.sooqr.com/ns/1.0");
		SimpleXMLElement::addNamespace('g', "http://base.google.com/ns/1.0");

		// set name of child element that is created when a node has multiple values
		SimpleXMLElement::setChildNodeName('node');
	}

	protected function setShop($shopId = null) {
		if( !empty($shopId) ) {

			$repo = $this->getShopRepository();

			$shop = $repo->find($shopId);

			if( !empty($shop) ) {
				$this->shop = $shop;
				return;
			}
		}

		$this->shop = Shopware()->Shop();
	}

	public function currentShopId()
	{
		return $this->shop->getId();
	}

	public function getPath()
	{
		$path = __DIR__ . "/../tmp";

		if( !file_exists($path) )
		{
			// make sure path exists
			@mkdir($path, 0777, true);
		}

		return $path;
	}

	public function getFilename()
	{
		$shopId = $this->currentShopId();
		return $this->getpath() . "/sooqr-{$shopId}.xml";
	}

	public function getTmpFilename()
	{
		$shopId = $this->currentShopId();
		$date = date('YmdHis');
		return $this->getpath() . "/tempfile-sooqr-{$shopId}-{$date}.xml";
	}

	public function getGzFilename()
	{
		return $this->getFilename() . ".gz";
	}

	public function getGzTmpFilename()
	{
		return $this->getTmpFilename() . ".gz";
	}

	public function getLockFile()
	{
		return $this->getPath() . "/sooqr.lock";
	}

	public function moveTmpFile($tmp)
	{
		// (over)write filename
		rename($tmp, $this->getFilename());

		// delete temp file
		@unlink($tmp);
	}

	public function cleanupOldTempfiles()
	{
		$path = $this->getPath();

		$files = scandir($path);

		// get tempfiles
		$files = array_filter($files, function($file) {
			$tempfileStart = "tempfile";
			return ( substr($file, 0, strlen($tempfileStart)) === $tempfileStart );
		});

		// remove all tempfiles
		foreach( $files as $file )
		{
			@unlink(Helpers::pathCombine($path, $file));
		}
	}

	public function needBuilding($maxSeconds = null)
	{
		if( !file_exists($this->getFilename()) ) return true;

		if( is_null($maxSeconds) )
		{
			$maxSeconds = Shopware()->Config()->get(ShopwareConfig::getName('time_interval'), 23 * 60 * 60); // in seconds
		}

		$lastModified = filemtime($this->getFilename());

		return (time() - $lastModified) > $maxSeconds;
	}

	public function getArticleRepository()
	{
		return $this->em->getRepository('Shopware\Models\Article\Article');
	}

	public function getShopRepository()
	{
		return $this->em->getRepository('Shopware\Models\Shop\Shop');
	}

	public function hideNoInstockConfig()
	{
		if( !isset($this->cache['config']) ) $this->cache['config'] = [];

		if( !isset($this->cache['config']['hideNoInstock']) )
		{
			$this->cache['config']['hideNoInstock'] = !!$this->config->get('hideNoInstock');
		}

		return $this->cache['config']['hideNoInstock'];
	}

	public function imageSizeConfig()
	{
		if( !isset($this->cache['config']) ) $this->cache['config'] = [];

		if( !isset($this->cache['config']['image_size']) )
		{
			$imageSize = strtolower(Shopware()->Config()->get(ShopwareConfig::getName('image_size'), "200"));

			$sizes = [
				's' => 200,
				'm' => 600,
				'l' => 1280
			];

			if( in_array($imageSize, [ 's', 'm', 'l' ] ) )
			{
				$this->cache['config']['image_size'] = $sizes[$imageSize];
			}
			else
			{
				$this->cache['config']['image_size'] = intval($imageSize);
			}
		}

		return $this->cache['config']['image_size'];
	}

	public function totalArticleDetails()
	{
		$sql = "SELECT count(*) AS count FROM s_articles_details" . ($this->hideNoInstockConfig() ? " WHERE instock > 0" : "");

		$result = $this->db->executeQuery($sql)->fetch();
		return empty($result['count']) ? 0 : $result['count'];
	}

	public function totalArticles()
	{
		$sql = "SELECT count(*) AS count FROM s_articles WHERE active = 1";

		if( $this->hideNoInstockConfig() )
		{
			$sql .= ' AND id NOT IN (SELECT articleID FROM s_articles_details GROUP BY articleID HAVING SUM(instock) < 1)';
		}

		$result = $this->db->executeQuery($sql)->fetch();
		return empty($result['count']) ? 0 : $result['count'];
	}

	public function getShopCategoryIds() {
		// get the category of the shop
		$mainCategoryId = $this->shop->getCategory()->getId();

		// get all categories
		$sql = "SELECT id, parent FROM s_categories";
		$categories = $this->db->executeQuery($sql)->fetchAll();

		// build category tree, and select the main category
		$tree = new CategoryTree($categories);
		$treeEntry = $tree->getById($mainCategoryId);

		// select all children of the mainCategory
		$children = $treeEntry->getDeepChildren();

		// get ids of the children
		$categoryIds = array_map(function($child) { return $child->getId(); }, $children);

		// add main if needed
		if( !in_array($mainCategoryId, $categoryIds) ) $categoryIds[] = $mainCategoryId;

		return $categoryIds;
	}

	/**
	 * TODO: create iterate of articles with only articles of subshop
	 */
	public function iterateArticles(callable $cb)
	{
		$limit = 100;
		$offset = 0;
		$ids = [];

		$categoryIdsIn = implode(",", $this->getShopCategoryIds());
		$articleRepo = $this->getArticleRepository();

		do
		{
			$sql = (
				"SELECT id FROM s_articles a " .

				// select only articles with stock (depending on config)
				($this->hideNoInstockConfig() ? 
					"JOIN ( " .
						"SELECT articleID FROM s_articles_details " .
						"GROUP BY articleID " .
						"HAVING SUM(instock) > 0 " .
					") b ON b.articleID = a.id "
					:
					""
				) .

				// select only articles that have a category in the tree of the shop category
				"JOIN ( " .
					"SELECT DISTINCT(articleID) FROM ( " .
						"SELECT ac.articleID FROM s_articles_categories ac WHERE ac.categoryID IN ({$categoryIdsIn}) " .
						"UNION " .
						"SELECT acr.articleID FROM s_articles_categories_ro acr WHERE acr.categoryID IN ({$categoryIdsIn}) " .
					") un " .
				") ca ON ca.articleID = a.id " .

				"WHERE a.active = 1 " .
				"LIMIT {$limit} OFFSET {$offset} "
			);

			// pluck ids of the returned rows
			$ids = array_map(function($row) { return $row['id']; }, $this->db->executeQuery($sql)->fetchAll());

			// select the doctrine article-models for the ids
			$articles = $articleRepo->findById($ids);

			foreach ($articles as $article)
			{
				call_user_func($cb, $article);
			}

			// flush changes
			$this->em->flush();

			// set offset for next batch
			$offset += $limit;
		}
		while(count($ids) > 0);
	}

	function echoFileChunked($filename, $returnBytes = false, $chunkSize = 1048576) {
	    $buffer = '';
	    $numberOfBytes = 0;
	    $handle = fopen($filename, 'rb');

	    if( $handle === false ) 
	    {
	        return false;
	    }

	    while( !feof($handle) ) 
	    {
	        $buffer = fread($handle, $chunkSize);
	        $this->echoDirect($buffer);

	        if( $returnBytes ) 
	        {
	            $numberOfBytes += strlen($buffer);
	        }
	    }

	    $status = fclose($handle);

	    if( $returnBytes && $status ) 
	    {
	        return $numberOfBytes; // return num. bytes delivered like readfile() does.
	    }

	    return $status;
	}

	public function getShopBaseUrl()
	{
		$host = $this->config->get("host");
		$baseUrl = $this->shop->getBaseUrl();

		$path = Helpers::pathCombine($host, $baseUrl);

		return "http://{$path}";
	}

	/**
	 * Build the url to an article
	 * @return string          Url to article
	 */
	public function getUrlForArticle($article)
	{
		$articleId = $article->getId();

		$db = Shopware()->Db();

		$sql = "SELECT path FROM s_core_rewrite_urls WHERE org_path = :org_path AND main = 1";
		$params = [ ":org_path" => "sViewport=detail&sArticle={$articleId}" ];

		$query = $db->executeQuery($sql, $params);
		$row = $query->fetch();

		return isset($row['path']) ? Helpers::pathCombine( $this->getShopBaseUrl(), $row['path'] ) : "";
	}

	public function selectThumbnail($thumbnails)
	{
		if( count($thumbnails) < 1 ) return '';

		// requested image size
		$imageSize = $this->imageSizeConfig();

		// calculate the difference for all thumbnail sizes
		$sizes = [];
		foreach ($thumbnails as $size => $url) 
		{
			// for now just get image with size closest to requested
			$dimensions = explode("x", $size); // [ width, height ]
			$sizes[$size] = abs($dimensions[0] - $imageSize) + abs($dimensions[1] - $imageSize);
		}

		// sort on closest
		asort($sizes);

		// return key of first element
		reset($sizes);
		$size = key($sizes);

		return $thumbnails[$size];
	}

	public function getImageLinkArticle($article)
	{
		// engine/Shopware/Controllers/Frontend/Detail.php on line 99 (sGetArticleById)
		// engine/Shopware/Components/Compatibility/LegacyStructConverter.php on line 375 (convertMediaStruct)

		try
		{
			$image = $article->getImages()->first();

			if( $image )
			{
				$media = $image->getMedia();

				$thumbnails = $media->getThumbnails();

				$path = $this->selectThumbnail($thumbnails);
				if( !$path ) return '';

				$host = Shopware()->Config()->get("host");

				// $path = $media->getPath();

				// dont follow urls, takes a looooong time
				// return $this->getUrlRedirectedTo("http://{$host}/{$path}");

				return "http://{$host}/{$path}";
			}
			
		}
		catch(\Doctrine\ORM\EntityNotFoundException $ex)
		{
			return '';
		}
		catch(Exception $ex)
		{
			return "";
		}

        return "";
	}

	/**
	 * Get url redirected to for an url
	 * @param  string  $url  Url to follow redirects
	 * @return string        Url redirected to
	 */
	public function getUrlRedirectedTo($url)
	{
		// http://stackoverflow.com/a/21032909/2492536
		
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url); 			// set url
	    curl_setopt($ch, CURLOPT_HEADER, true); 		// get header
	    curl_setopt($ch, CURLOPT_NOBODY, true); 		// do not include response body
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // do not show in browser the response
	    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow any redirects
	    curl_exec($ch);
	    $newUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); //extract the url from the header response
	    curl_close($ch);

	    return $newUrl;
	}

	public function getXmlHeader()
	{
		$config = [
			'system' => 'Shopware',
			'extension' => $this->pluginJson->getLabel('en'),
			'extension_version' => $this->pluginJson->getVersion(),
			'store' => $this->shop->getName(),
			'url' => $this->getShopBaseUrl(),
			'token' => Helpers::randomString(16),
			'products_total' => $this->totalArticles(),
			'product_details_total' => $this->totalArticleDetails(),
			'products_limit' => 0,
			'date_created' => date('Y-m-d H:i:s'),
			'processing_time' => 0
		];

		$header  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
		$header .= "<rss xmlns:g=\"http://base.google.com/ns/1.0\" xmlns:sqr=\"http://base.sooqr.com/ns/1.0\" version=\"2.0\">";

		$configElement = new SimpleXMLElement("<config></config>");

		foreach ($config as $key => $value)
		{
			$configElement->addChild("{$key}", $value);
		}

		$header .= $configElement->toElementString();

		$header .= "<products>";

		return $header;
	}

	public function getXmlFooter()
	{
		return "</products></rss>";
	}

	protected function getPrice($item, $article, $detail)
	{
		$price = $detail->getPrices()->first();

		$pseudoPrice = $price->getPseudoPrice();
		$price = $price->getPrice();

		$tax = $article->getTax();
		$taxPercentage = $tax->getTax();

		$item->addChild("price_gross", $price);

		// price is gross, calculate net
		$price += $price * $taxPercentage / 100;

		$item->addChild("price", round($price, 2));

		if( $pseudoPrice > 0 ) // has a discount
		{
			$item->addChild("normal_price_gross", $pseudoPrice);

			// pseudoPrice is gross, calculate net
			$pseudoPrice += $pseudoPrice * $taxPercentage / 100;

			$item->addChild("normal_price", round($pseudoPrice, 2));
		}
	}

	protected function getConfiguratorOptions($item, $article)
	{
		$set = $article->getConfiguratorSet();

		if( empty($set) ) return;

		$groups = $set->getGroups();
		
		$options = array_reduce($set->getOptions()->toArray(), function($arr, $option) {

			$id = $option->getGroup()->getId();
			if( !isset($arr[$id]) ) $arr[$id] = [];

			$arr[$id][] = $option->getName();

			return $arr;

		}, []);

		foreach( $groups as $group )
		{
			$groupId = $group->getId();

			if( isset($options[$groupId]) )
			{
				$groupOptions = $options[$groupId];

				$item->addMultiChild($this->escapeXmlTag($group->getName()), $groupOptions);
			} 
			else 
			{
				$item->addChild($this->escapeXmlTag($group->getName()), "");
			}
		}
	}

	protected function getFilterValues($item, $article)
	{
		$propertyGroup = $article->getPropertyGroup();

		if( empty($propertyGroup) ) return;

		$options = $propertyGroup->getOptions();

		$values = array_reduce($article->getPropertyValues()->toArray(), function($arr, $value) {

			$id = $value->getOption()->getId();
			if( !isset($arr[$id]) ) $arr[$id] = [];

			$arr[$id][] = $value->getValue();

			return $arr;

		}, []);

		foreach( $options as $option )
		{
			$optionId = $option->getId();

			if( isset($values[$optionId]) )
			{
				$optionValues = $values[$optionId];

				$item->addMultiChild($this->escapeXmlTag($option->getName()), $optionValues);
			}
		}
	}

	public function getCategories($item, $article)
	{
		$categoryParents = array_map(function($parent) { return (int)trim($parent); }, explode(',', Shopware()->Config()->get(ShopwareConfig::getName('category_parents'), "1")));

		$articleCategories = $article->getCategories();

		$levels = [];

		// loop through all categories of the article
		foreach( $articleCategories as $category )
		{
			$categories = [ $category->getName() ];

			// get categories till at root, 
			// or till a parent category from the config is reached
			while($category = $category->getParent())
			{
				if( in_array($category->getId(), $categoryParents) ) break;
				array_unshift($categories, $category->getName());
			}

			// gather the categories in the correct level
			for ($i=0; $i < count($categories); $i++) 
			{ 
				$levels[$i][] = $categories[$i];
			}
		}
		
		foreach ($levels as $level => $categories)
		{
			$item->addMultiChild("category{$level}", $categories, true);
		}
	}

	/**
	 * Get info from s_articles_attributes table
	 */
	public function getExtraAttributes($item, $detail)
	{
		$attribute = $detail->getAttribute();

		if( !empty($attribute) )
		{
			for ($i=0; $i < 20; $i++)
			{
				$method = "getAttr{$i}";
				
				if( method_exists($attribute, $method) )
				{
					$value = trim($attribute->{$method}());

					if( !empty($value) )
					{
						$item->addChildIfNotEmpty("attribute{$i}", $value);
					}
				}
			}
		}
	}

	/**
	 * Add stock and stock status for an article to an item
	 * The stock is the total of all details of the article
	 */
	public function getStockArticle($item, $article, $details)
	{
		// Boolean for when the stock is <= 0, 
		// if the item should be available or not
		$lastStock = (bool)$article->getLastStock();

		$stock = array_reduce($details->toArray(), function($total, $detail) { 
			return $total + intval($detail->getInStock()); 
		}, 0);

		$item->addChild('stock', $stock);
		$item->addChild('stock_status', $stock > 0 || $lastStock === false ? 1 : 0);
	}

	/**
	 * Add stock and stock status for an detail to an item
	 * The stock is the stock of only one detail
	 */
	public function getStockDetail($item, $article, $detail)
	{
		// Boolean for when the stock is <= 0, 
		// if the item should be available or not
		$lastStock = (bool)$article->getLastStock();

		$stock = $detail->getInStock();
		$stock = empty($stock) ? 0 : intval($stock);

		$item->addChild('stock', $stock);
		$item->addChild('stock_status', $stock > 0 || $lastStock === false ? 1 : 0);
	}

	public function buildItems($article)
	{
		$mainDetail = $article->getMainDetail();
		$details = $article->getDetails();
		$supplier = $article->getSupplier();

		$item = new SimpleXMLElement("<item></item>");

		$item->addChildEscape("id", $mainDetail->getNumber());
		$item->addChildIfNotEmpty("title", strip_tags($article->getName()));
		$item->addChildIfNotEmpty("description_short", strip_tags($article->getDescription()));
		$item->addChildIfNotEmpty("description", strip_tags($article->getDescriptionLong()));
		$item->addChildIfNotEmpty("meta_title", strip_tags($article->getMetaTitle()));
		$item->addChildIfNotEmpty("keywords", $article->getKeywords());

		$item->addChildIfNotEmpty("brand", $supplier ? $supplier->getName() : "");

		$item->addChildIfNotEmpty("supplier_number", $mainDetail->getSupplierNumber());
		$item->addChildIfNotEmpty("ean", $mainDetail->getEan());
		$item->addChildIfNotEmpty("width", $mainDetail->getWidth());
		$item->addChildIfNotEmpty("height", $mainDetail->getHeight());
		$item->addChildIfNotEmpty("weight", $mainDetail->getWeight());
		$item->addChildIfNotEmpty("length", $mainDetail->getLen());
		$item->addChildIfNotEmpty("additional_text", $mainDetail->getAdditionalText());
		
		$item->addChildWithCDATA("url", $this->getUrlForArticle($article));
		$item->addChildWithCDATA("image_link", $this->getImageLinkArticle($article));

		$this->getPrice($item, $article, $mainDetail);
		$this->getConfiguratorOptions($item, $article);
		$this->getFilterValues($item, $article);
		$this->getCategories($item, $article);
		$this->getExtraAttributes($item, $mainDetail);
		$this->getStockArticle($item, $article, $details);

		// return xml element without the xml header
		return $item->toElementString();
	}

	protected function echoDirect($buffer)
	{
        echo $buffer;
        ob_flush();
        flush();
	}

	protected function outputString($tmp, $str, $echo = false)
	{
		file_put_contents($tmp, $str, FILE_APPEND);
		if( $echo ) $this->echoDirect($str);
	}

	public function escapeXmlTag($str)
	{
		// http://www.w3schools.com/xml/xml_elements.asp
		
		// replace bad chars with underscores
		// (not alfanumeric, underscore, hyphen or period)
		$str = preg_replace("/[^A-Za-z0-9\.\-_]/", "_", $str);
		
		// element name can't start with xml or XML or Xml
		// element must start with a letter or an underscore
		if( 
			strtolower(substr($str, 0, 3)) == "xml" ||
			!in_array($str[0], str_split("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_"))
		) 
		{
			$str = "_" . $str;
		}

		return $str;
	}

	public function buildXml($echo = false, $force = false)
	{
		$acquired = $this->lock->waitTillAcquired();

		if( $acquired === false ) return;

		// if a lock wasn't acquired at first, 
		// the xml is probably already build again, 
		// so test again if it needs to be build
		if( !$force && !$this->needBuilding() )
		{
			if( $echo ) $this->echoFileChunked($this->getFilename());
		}
		else
		{
			$this->cleanupOldTempfiles();

			$tmp = $this->getTmpFilename();

			$this->outputString($tmp, $this->getXmlHeader(), $echo);

			$this->iterateArticles(function($article) use ($tmp, $echo) {

				$items = $this->buildItems($article);

				$this->outputString($tmp, $items, $echo);
			});

			$this->outputString($tmp, $this->getXmlFooter(), $echo);

			$this->moveTmpFile($tmp);
		}

		$this->lock->removeLock();
	}

	public function buildGz()
	{
		if( $this->needBuilding() )
		{
			$this->buildXml();
		}

		$gzAlreadyBuild = file_exists($this->getGzFilename()) && filemtime($this->getFilename()) < filemtime($this->getGzFilename());

		if( !$gzAlreadyBuild )
		{
			$this->lock->waitTillAcquired();

			$tmp = $this->getGzTmpFilename();

			Gzip::fromFile($this->getFilename(), $tmp);

			rename($tmp, $this->getGzFilename());
			@unlink($tmp);

			$this->lock->removeLock();
		}

		return $this->getGzFilename();
	}

	public function outputXml($force = false, $gzip = false)
	{
		$filename = $this->getFilename();

		if( $gzip )
		{
			header("Content-Type: application/gzip");

			$this->echoFileChunked($this->buildGz());
		}
		else
		{
			header('Content-Type: application/xml');

			if( $force || $this->needBuilding() )
			{
				$echoOutput = true;
				$this->buildXml($echoOutput, $force);
			} 
			else 
			{
				$this->echoFileChunked($filename);
			}
		}
	}
}
