<?php

namespace Shopware\SitionSooqr\Components;

use SimpleXMLElement;

Class SimpleXMLElementExtended extends SimpleXMLElement {

	static $namespaces = [];

	public static function addNamespace($ns, $url)
	{
		static::$namespaces[$ns] = $url;
	}

	public static function getNamespace($ns)
	{
		return static::$namespaces[$ns];
	}

	public static function getNamespaceXmlns()
	{
		$xmlns = [];

		foreach (static::$namespaces as $ns => $url) 
		{
			$xmlns[] = "xmlns:{$ns}=\"{$url}\"";
		}

		return $xmlns;
	}

	/**
	 * addChild normally removes namespaces from the name it doesn't know
	 * This makes it know the namespace, so it doesn't delete it
	 */
	public function addChildNs($name, $value)
	{
		if( stripos($name, ':') )
		{
			$parts = explode(':', $name);
			$ns = $parts[0];

			return $this->addChild($name, $value, static::getNamespace($ns));
		}

		return $this->addChild($name, $value);
	}

	/**
	* Adds a child with $value inside CDATA
	* http://stackoverflow.com/questions/6260224/how-to-write-cdata-using-simplexmlelement
	* @param  string  $name
	* @param  string  $value
	*/
	public function addChildWithCDATA($name, $value = null) 
	{
		$newChild = $this->addChildNs($name);

		if( $newChild !== null ) 
		{
			$node = dom_import_simplexml($newChild);
			$owner = $node->ownerDocument;
			$node->appendChild($owner->createCDATASection($value));
		}

		return $newChild;
	}

	public function addChildEscape($name, $value = null)
	{
		if( $this->isXmlSafe($value) )
		{
			return $this->addChildNs($name, $value);
		}
		else
		{
			return $this->addChildWithCDATA($name, $value);
		}
	}

	/**
	 * Only add node when the value is a real value
	 * 
	 * Check if value contains forbidden characters
	 * If not add without cdata
	 */
	public function addChildIfNotEmpty($name, $value = null)
	{
		$value = trim($value);
		if( empty($value) ) return null;

		return $this->addChildEscape($name, $value);
	}

	/**
	 * Check if value needs escaping
	 */
	public function isXmlSafe($value)
	{
		return htmlspecialchars($value) === $value;
	}

	/**
	 * Output element and all subnodes as string
	 * Don't output a xml header
	 */
	public function toElementString()
	{
		// return xml element without the xml header
		$dom = dom_import_simplexml($this);
		$element = $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);

		// remove namespaces on single elements
		$element = str_replace(static::getNamespaceXmlns(), '', $element);

		return $element;
	}
}
