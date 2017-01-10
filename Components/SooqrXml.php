<?php

namespace Shopware\SitionSooqr\Components;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Shopware\SitionSooqr\Components\SimpleXMLElementExtended as SimpleXMLElement;
use Shopware\SitionSooqr\Components\Locking;
use Shopware\SitionSooqr\Components\Gzip;
use Shopware\SitionSooqr\Components\ShopwareConfig;
use Shopware\SitionSooqr\Components\Helpers;
use Shopware\SitionSooqr\Components\PluginJson;

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

	public function __construct()
	{
		set_time_limit(60 * 60); // 1 hour

		$this->em = Shopware()->Models(); // modelManager

		$this->lock = new Locking( $this->getLockFile() );

		$this->shop = Shopware()->Shop();
		$this->config = Shopware()->Config();
		$this->db = Shopware()->Db();
		$this->pluginJson = new PluginJson;
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

	/**
	 * Get active articles count
	 * @return  int  number of active articles
	 */
	public function getNumberOfArticles()
	{
		$qb = $this->em->createQueryBuilder();
		$qb->select($qb->expr()->count('a.id'));
		$qb->from('Shopware\Models\Article\Article', 'a');
		$qb->where("a.active = 1");

		return $qb->getQuery()->getSingleScalarResult();
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

	public function totalArticleDetails()
	{
		$sql = "SELECT count(*) AS count FROM s_articles_details" . ($this->hideNoInstockConfig() ? " WHERE instock > 0" : "");

		$result = $this->db->executeQuery($sql)->fetch();
		return empty($result['count']) ? 0 : $result['count'];
	}

	public function totalArticles()
	{
		$sql = "SELECT count(*) AS count FROM s_articles";

		if( $this->hideNoInstockConfig() )
		{
			$sql .= ' WHERE id NOT IN (SELECT articleID FROM s_articles_details GROUP BY articleID HAVING SUM(instock) < 1)';
		}

		$result = $this->db->executeQuery($sql)->fetch();
		return empty($result['count']) ? 0 : $result['count'];
	}

	public function iterateArticles(callable $cb)
	{
		// http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/batch-processing.html

		$batchSize = 20;
		$i = 0;

		$dql = "SELECT a FROM Shopware\Models\Article\Article a " .
			"WHERE a.active = 1 ";

		// if articles without stock should be hidden,
		// get all article ids of articles that have no stock
		// then check the articles are not in the articleIds
		if( $this->hideNoInstockConfig() )
		{
			$articleIds = array_map(
				function($row) { return $row['articleID']; },
				$this->db->executeQuery(
					// get the id for articles where no detail has any stock
					"SELECT articleID FROM s_articles_details " .
					"GROUP BY articleID " .
					"HAVING SUM(instock) < 1"
				)->fetchAll()
			);

			// build IN query
			$articleIds = implode(",", $articleIds);
			$dql .= "AND a.id NOT IN ({$articleIds})";

			$articleIds = null;
		}

		$q = $this->em->createQuery($dql);

		foreach( $q->iterate() as $row ) 
		{
		    $article = $row[0];
		    // $user->increaseCredit();
		    // $user->calculateNewBonuses();
		   	// echo $article->getName() . "<br />";
		    call_user_func($cb, $article);

		    if( ($i % $batchSize) === 0 ) 
		    {
		        $this->em->flush(); // Executes all updates.
		        $this->em->clear(); // Detaches all objects from Doctrine!
		    }

		    ++$i;
		}

		$this->em->flush();
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

	public function getImageurlForArticle($article)
	{
		// engine/Shopware/Controllers/Frontend/Detail.php on line 99 (sGetArticleById)
		// engine/Shopware/Components/Compatibility/LegacyStructConverter.php on line 375 (convertMediaStruct)
		
		$image = $article->getImages()->first();

		if( $image )
		{
			$media = $image->getMedia();

			$host = Shopware()->Config()->get("host");

			$path = $media->getPath();

			// dont follow urls, takes a looooong time
			// return $this->getUrlRedirectedTo("http://{$host}/{$path}");
			
			return "http://{$host}/{$path}";
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
			$configElement->addChildEscape("sqr:{$key}", $value);
		}

		$header .= $configElement->toElementString();

		$header .= "<products>";

		return $header;
	}

	public function getXmlFooter()
	{
		return "</products></rss>";
	}

	protected function getPrice($item, $article)
	{
		$mainDetail = $article->getMainDetail();
		$price = $mainDetail->getPrices()->first();

		$pseudoPrice = $price->getPseudoPrice();
		$price = $price->getPrice();

		$tax = $article->getTax();
		$taxPercentage = $tax->getTax();

		// price is gross, calculate net
		$price += $price * $taxPercentage / 100;

		$item->addChild("price", round($price, 2));

		if( $pseudoPrice > 0 ) // has a discount
		{
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

				if( count($groupOptions) === 1 )
				{
					$item->addChildIfNotEmpty($this->escapeXmlTag($group->getName()), $groupOptions[0]);
				}
				else if( count($groupOptions) > 1 )
				{
					$groupItem = $item->addChild($this->escapeXmlTag($group->getName() . "s"));

					foreach( $groupOptions as $key => $groupOption ) 
					{
						$groupItem->addChildIfNotEmpty($this->escapeXmlTag($group->getName()), $groupOption);
					}
				}
			} else {
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

				if( count($optionValues) === 1 )
				{
					$item->addChildIfNotEmpty($this->escapeXmlTag($option->getName()), $optionValues[0]);
				}
				else if( count($optionValues) > 1 )
				{
					$groupItem = $item->addChild($this->escapeXmlTag($option->getName() . "s"));

					foreach( $optionValues as $key => $groupOption ) 
					{
						$groupItem->addChildIfNotEmpty($this->escapeXmlTag($option->getName()), $groupOption);
					}
				}
			} else {
				// $item->addChild($this->escapeXmlTag($option->getName()), "");
			}
		}
	}

	public function getCategories($item, $article)
	{
		$categoryParents = array_map(explode(Shopware()->Config()->get(ShopwareConfig::getName('category_parents'), "1"), ","), function($parent) { return (int)trim($parent); });

		$articleCategories = $article->getCategories();

		$xmlCategories = [];
		$xmlSubCategories = [];

		foreach( $articleCategories as $category )
		{
			$categories = [ $category->getName() ];

			while($category = $category->getParent())
			{
				if( in_array($category->getId(), $categoryParents) ) break;
				array_unshift($categories, $category->getName());
			}

			$xmlCategories = array_merge($xmlCategories, array_slice($categories, 0, 2));

			if( count($categories) > 2 )
			{
				$xmlSubCategories = array_merge($xmlSubCategories, array_slice($categories, 2));
			}
		}
		
		if( count($xmlCategories) > 1 )
		{
			$categoriesElement = $item->addChild("categories");

			foreach( $xmlCategories as $key => $category ) 
			{
				$categoriesElement->addChildIfNotEmpty('category', $category);
			}
		} 
		else 
		{
			$item->addChildIfNotEmpty('category', isset($xmlCategories[0]) ? $xmlCategories[0] : "");
		}

		if( count($xmlSubCategories) > 1 )
		{
			$subCategoriesElement = $item->addChild("subcategories");

			foreach( $xmlSubCategories as $key => $subCategory ) 
			{
				$subCategoriesElement->addChildIfNotEmpty('subcategory', $subCategory);
			}
		} 
		else 
		{
			$item->addChildIfNotEmpty('subcategory', isset($xmlSubCategories[0]) ? $xmlSubCategories[0] : "");
		}
	}

	/**
	 * Get info from s_articles_attributes table
	 */
	public function getExtraAttributes($item, $mainDetail)
	{
		$attribute = $mainDetail->getAttribute();

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

	public function buildItem($article)
	{
		$mainDetail = $article->getMainDetail();
		$supplier = $article->getSupplier();

		$item = new SimpleXMLElement("<item></item>");

		$item->addChild("id", $mainDetail->getNumber());
		$item->addChildIfNotEmpty("title", $article->getName());
		$item->addChildIfNotEmpty("description_short", $article->getDescription());
		$item->addChildIfNotEmpty("description", $article->getDescriptionLong());
		$item->addChildIfNotEmpty("meta_title", $article->getMetaTitle());
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
		$item->addChildWithCDATA("image_link", $this->getImageurlForArticle($article));

		$this->getPrice($item, $article);
		$this->getConfiguratorOptions($item, $article);
		$this->getFilterValues($item, $article);
		$this->getCategories($item, $article);
		$this->getExtraAttributes($item, $mainDetail);

		// return xml element without the xml header
		// $dom = dom_import_simplexml($item);
		// return $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
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

	public function buildXml($echo = false)
	{
		$acquired = $this->lock->waitTillAcquired();

		if( $acquired === false ) return;

		// if a lock wasn't acquired at first, 
		// the xml is probably already build again, 
		// so test again if it needs to be build
		if( !$this->needBuilding() )
		{
			if( $echo ) $this->echoFileChunked($this->getFilename());
		}
		else
		{
			$this->cleanupOldTempfiles();

			$tmp = $this->getTmpFilename();

			$this->outputString($tmp, $this->getXmlHeader(), $echo);

			$this->iterateArticles(function($article) use ($tmp, $echo) {

				$item = $this->buildItem($article);

				$this->outputString($tmp, $item, $echo);
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

	public function outputXml($gzip = false)
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

			if( $this->needBuilding() )
			{
				$echoOutput = true;
				$this->buildXml($echoOutput);
			} 
			else 
			{
				$this->echoFileChunked($filename);
			}
		}
	}
}
