<?php

namespace Shopware\SitionSooqr\Components;

use Shopware_Components_Plugin_Bootstrap;
use Shopware\Models\Config\Element;

class ShopwareConfig 
{
	public static $prefix = "Sition_SitionSooqr";

	protected $config;

	public function __construct(Shopware_Components_Config $config)
	{
		$this->config = $config;
	}

	public function get($key, $default = null)
	{
		$namedKey = static::getName($key);
		return $this->config->get( (in_array($namedKey, $this->getElementKeys()) ? $namedKey : $key), $default );	
	}

	public function getConfigShop($shopId = null)
	{
		// take current shop if no shop id is given
		if( is_null($shopId) ) $shopId = Shopware()->Shop()->getId();

		$db = Shopware()->Db();

		// build placeholders array
		$elementKeys = $this->getElementKeys();
		$questionMarks = rtrim(array_reduce($elementKeys, function($str, $e) { return $str . "?,"; }, ""), ",");

		$sql = "SELECT e.name, e.value as defaultValue, v.value FROM s_core_config_elements e " . 
			   "LEFT JOIN s_core_config_values v ON e.id = v.element_id AND v.shop_id = ? " .
			   "WHERE e.name IN ({$questionMarks})";

		// get values for the placeholders
		$params = $elementKeys;
		array_unshift($params, $shopId);

		// run query
		$results = $db->query($sql, $params)->fetchAll();

		// build key/value array
        return array_reduce($results, function($array, $item) {
        	$default = explode('"', $item['defaultValue']);
        	$default = isset($default[1]) ? $default[1] : "";

        	$array[ $item['name'] ] = ( is_null($item['value']) ) ? $default : $item['value'];

        	return $array; 
        }, []);
	}

    /**
     * Create Shopware Backend Config menu for plugin
     */
	public function createConfig( Shopware_Components_Plugin_Bootstrap $bootstrap )
	{
		$settings = $this->getSettings();

		$elements = $this->getElements($settings);

		$form = $bootstrap->Form();

		foreach( $elements as $key => $element ) 
		{
			// same as $form->setElement() with $element array values as arguments
			call_user_func_array([ $form, 'setElement' ], $element);
		}

		$this->translate($form);
	}

	public function translate($form)
	{
		$shopRepository = Shopware()->Models()->getRepository('\Shopware\Models\Shop\Locale');
 	
		$translations = $this->getTranslations();
 
	    // iterate the languages
	    foreach( $translations as $locale => $snippets ) 
	    {
	        $localeModel = $shopRepository->findOneBy([ 'locale' => $locale ]);
	 
	        // not found? continue with next language
	        if( $localeModel === null )
	        {
	            continue;
	        }
	 
	        // iterate all snippets of the current language
	        foreach( $snippets as $element => $snippet ) 
	        {
	            // get the form element by name
	            $elementModel = $form->getElement($element);
	 
	            // not found? continue with next snippet
	            if( $elementModel === null ) 
	            {
	                continue;
	            }
	 
	            // create new translation model
	            $translationModel = new \Shopware\Models\Config\ElementTranslation();
	            $translationModel->setLabel($snippet);
	            $translationModel->setLocale($localeModel);
	 
	            // add the translation to the form element
	            $elementModel->addTranslation($translationModel);
	        }
	    }
	}

	public function getElementKeys()
	{
		return array_map(function($element) {
			return $element[1];
		}, $this->getElements());
	}

	public static function getName($name)
	{
		return static::$prefix . "_" . $name;
	}

	public function getElements($settings = null)
	{
		return [
			[ 
				'interval', 
				static::getName('time_interval'),
		        [
		            'label' => 'Time interval between generating xml',
		            'scope' => Element::SCOPE_SHOP,
		            'value' => $this->getDefault($settings, 'time_interval', 23 * 60 * 60),
		            'description' => 'Provide a time value in number of seconds',
		            'required' => true
		        ]
		    ],
			[ 
				'text', 
				static::getName('account_identifier'),
		        [
		            'label' => 'Account identifier',
		            'scope' => Element::SCOPE_SHOP,
		            'value' => $this->getDefault($settings, 'account_identifier', null),
		            'description' => 'Provide your Sooqr account id',
		            'required' => true
		        ]
		    ],
			[ 
				'text', 
				static::getName('category_parents'),
		        [
		            'label' => 'Category parents',
		            'scope' => Element::SCOPE_SHOP,
		            'value' => $this->getDefault($settings, 'category_parents', "1"),
		            'description' => 'Provide the ids of the categories where the main categories belong to'
		        ]
		    ],
		];
	}

	public function getTranslations()
	{
	    //contains all translations
	    return [
	        'en_GB' => [
	            static::getName('time_interval') => 'Time interval between generating xml',
	            static::getName('account_identifier') => "Account identifier",
	            static::getName('api_key') => "Api Key",
	        ],
	        'de_DE' => [
	            static::getName('time_interval') => 'Das Zeitintervall zwischen der Generierung xml',
	            static::getName('account_identifier') => "Kontokennung",
	            static::getName('api_key') => "Api-Schlüssel",
	        ],
	        'nl_NL' => [
	            static::getName('time_interval') => 'Tijd interval tussen het genereren van het xml',
	            static::getName('account_identifier') => "Account identifier",
	            static::getName('api_key') => "Api Key",
	        ],
	    ];
	}


	/**
	 * Get default settings from a json file,
	 * so you don't have to put in the settings after every reinstall
	 */
	protected function getSettings()
	{
		$path = __DIR__ . "/../config.json";
		if( file_exists($path) )
		{
			return json_decode(file_get_contents($path), true);
		}

		return null;
	}

	/**
	 * Get a default value from the provided settings
	 */
	protected function getDefault($settings, $key, $default)
	{
		return isset($settings[$key]) ? $settings[$key] : $default;
	}
}