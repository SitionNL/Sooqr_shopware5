<?php

namespace Shopware\SitionSooqr\Components;

class Helpers
{
	/**
	 * path_combine
	 *
	 * Takes one or more paths as arguments and puts them together,
	 * so there are no slash conflicts between path-parts
	 *
	 * @param  string/array  Variable number of arguments. Arguments must be a path string or an array of path strings
	 * @return string        combined path  
	 */
	public function pathCombine()
	{
		$paths = array_values( Helpers::arrayFlatten(func_get_args()) );
		$newPaths = array();
		$nrOfPaths = count($paths);

		for ($i=0; $i < $nrOfPaths; $i++) 
		{
			$path = $paths[$i];

			$path = str_replace("\\", "/", $path);

			if( $i > 0 ) 
			{
				$path = ltrim($path, "/");
			}

			if( $i < ($nrOfPaths - 1) ) 
			{
				$path = rtrim($path, "/");
			}
			
			$newPaths[] = $path;
		}

		return implode("/", $newPaths);
	}

	public function arrayFlatten(array $arr) 
	{
	    $arr = array_reduce($arr, function($a, $item) {

	        if( is_array($item) ) $item = Helpers::arrayFlatten($item);
	        return array_merge($a, (array)$item);

	    }, []);

	    return $arr;
	}

}
