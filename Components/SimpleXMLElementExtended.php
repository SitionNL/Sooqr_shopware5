<?php

namespace Shopware\SitionSooqr\Components;

use SimpleXMLElement;

Class SimpleXMLElementExtended extends SimpleXMLElement {

	static $namespaces = [];
	static $childNodeName = 'node';

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

	public static function setChildNodeName($name)
	{
		static::$childNodeName = $name;
	}

	public static function setChildNodeName()
	{
		return static::$childNodeName;
	}

	/**
	 * addChild normally removes namespaces from the name it doesn't know
	 * This makes it know the namespace, so it doesn't delete it,
	 * This makes it possible to generate xml snippets, instead of generating the xml in one go
	 */
	public function addChildNs($name, $value = '')
	{
		if( stripos($name, ':') )
		{
			$parts = explode(':', $name);
			$ns = $parts[0];

			return parent::addChild($name, $value, static::getNamespace($ns));
		}

		return parent::addChild($name, $value);
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

	/**
	 * Add CDATA if necessary
	 */
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
	 * Overwrite default addChild implementation
	 */
	public function addChild($name, $value = '')
	{
		return $this->addChildEscape($name, $value);
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

	/**
	 * Add a child with multiple values
	 * @param string  $name       Name of the element
	 * @param array   $values     Values for the element
	 * @param boolean $alwaysShow If true, there is always an empty element created (default: false)
	 */
	public function addMultiChild($name, array $values = [], $alwaysShow = false)
	{
		if( count($values) === 1 )
		{
			return $this->addChildEscape($name, $values[0]);
		}
		else if( count($values) === 0 )
		{
			return $alwaysShow ? $this->addChildEscape($name) : $this->addChildIfNotEmpty($name);
		}


		$child = $this->addChildEscape($name);

		foreach ($values as $value)
		{
			$child->addChild(static::$childNodeName, $value);
		}

		return $child;
	}
}
