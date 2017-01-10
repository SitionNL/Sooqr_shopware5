<?php

namespace Shopware\SitionSooqr\Components;

class PluginJson
{
	public function __construct()
	{

	}

	public function read($path = null)
	{
		if( is_null($path) ) $path = __DIR__ . "/../plugin.json";

		if( !file_exists($path) )
		{
			throw new Exception("plugin.json file with path {$path} doesn't exist");
		}

		try 
		{
			$data = json_decode(file_get_contents($path), true);
			return $data;
		} 
		catch(Exception $ex) 
		{
			throw new Exception("Can't read plugin.json", 0, $ex);
		}
	}

	public function getVersion()
	{
		$data = $this->read();
		return !empty($data['currentVersion']) ? $data['currentVersion'] : "";
	}

	public function getLabel($lang = 'de')
	{
		$data = $this->read();

		if( !empty($data['label']) ) 
		{
			$label = $data['label'];

			if( is_array($label) )
			{
				if( !empty($label[$lang]) )
				{
					return $label[$lang];
				}
				else
				{
					return array_shift(array_values($label));
				}
			}
			else if( is_string($label) )
			{
				return $label;
			}
		}

		return '';
	}
}
