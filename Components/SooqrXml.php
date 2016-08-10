<?php

namespace Shopware\SitionSooqr\Components;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Shopware\SitionSooqr\Components\SimpleXMLElementExtended as SimpleXMLElement;

class SooqrXml
{
	protected $em;

	public function __construct()
	{
		set_time_limit(60 * 60); // 1 hour

		$this->em = Shopware()->Models(); // modelManager
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
		return $this->getpath() . "/sooqr.xml";
	}

	public function getTmpFilename()
	{
		$date = date('YmdHis');
		return $this->getpath() . "/sooqr-{$date}.xml";
	}

	public function moveTmpFile($tmp)
	{
		// (over)write filename
		rename($tmp, $this->getFilename());

		// delete temp file
		@unlink($tmp);
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

	public function iterateArticles(callable $cb)
	{
		// http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/batch-processing.html

		$batchSize = 20;
		$i = 0;

		$dql = "SELECT a FROM Shopware\Models\Article\Article a " .
			"WHERE a.active = 1 ";

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
	        echo $buffer;
	        ob_flush();
	        flush();

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

	/**
	 * Build the url to an article
	 * @return string          Url to article
	 */
	public function getUrlForArticle($article)
	{
		$articleId = $article->getId();

		$host = Shopware()->Config()->get("host");

		$db = Shopware()->Db();

		$sql = "SELECT path FROM s_core_rewrite_urls WHERE org_path = :org_path";
		$params = [ ":org_path" => "sViewport=detail&sArticle={$articleId}" ];

		$query = $db->executeQuery($sql, $params);
		$row = $query->fetch();

		return isset($row['path']) ? "http://{$host}/{$row['path']}" : "";
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
		return "<?xml version=\"1.0\" standalone=\"yes\"?>\n<items>";
	}

	public function getXmlFooter()
	{
		return "</items>";
	}

	public function buildItem($article)
	{
		$mainDetail = $article->getMainDetail();
		$supplier = $article->getSupplier();

		$item = new SimpleXMLElement("<item></item>");

		$item->addChild("id", $mainDetail->getNumber());
		$item->addChild("name", $article->getName());
		$item->addChild("description", $article->getDescription());
		$item->addChild("supplier", $supplier ? $supplier->getName() : "");
		$item->addChildWithCDATA("url", $this->getUrlForArticle($article));
		$item->addChildWithCDATA("imageurl", $this->getImageurlForArticle($article));

		// return xml element without the xml header
		$dom = dom_import_simplexml($item);
		return $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
	}

	protected function outputString($filename, $str, $echo = false)
	{
		file_put_contents($filename, $str, FILE_APPEND);
		if( $echo ) echo $str;
	}

	public function buildXml($echo = false)
	{
		$tmp = $this->getTmpFilename();

		$this->outputString($tmp, $this->getXmlHeader(), $echo);

		$this->iterateArticles(function($article) use ($tmp, $echo) {

			$item = $this->buildItem($article);

			$this->outputString($tmp, $item, $echo);
		});

		$this->outputString($tmp, $this->getXmlFooter(), $echo);

		$this->moveTmpFile($tmp);
	}

	public function outputXml()
	{
		$filename = $this->getFilename();

		header('Content-Type: application/xml');
		
		// if( file_exists($filename) )
		// {
		// 	$this->echoFileChunked($filename);
		// } 
		// else 
		// {
			$echoOutput = true;
			$this->buildXml($echoOutput);
		// }
	}
}