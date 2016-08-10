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
	public function addChildWithCDATA($name, $value = null) {
		$newChild = $this->addChild($name);

		if( $newChild !== null ) 
		{
			$node = dom_import_simplexml($newChild);
			$owner = $node->ownerDocument;
			$node->appendChild($owner->createCDATASection($value));
		}

		return $newChild;
	}
}