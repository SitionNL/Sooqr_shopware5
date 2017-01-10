<?php

namespace Shopware\SitionSooqr\Components;

use SimpleXMLElement;

Class SimpleXMLElementExtended extends SimpleXMLElement {

	/**
	* Adds a child with $value inside CDATA
	* http://stackoverflow.com/questions/6260224/how-to-write-cdata-using-simplexmlelement
	* @param  string  $name
	* @param  string  $value
	*/
	public function addChildWithCDATA($name, $value = null) 
	{
		$newChild = $this->addChild($name);

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
			return $this->addChild($name, $value);
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
		return $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
	}
}