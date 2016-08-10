<?php

class PluginTest extends Shopware\Components\Test\Plugin\TestCase
{
    protected static $ensureLoadedPlugins = array(
        'SitionSooqr' => array(
        )
    );

    public function setUp()
    {
        parent::setUp();

        $helper = \TestHelper::Instance();
        $loader = $helper->Loader();


        $pluginDir = getcwd() . '/../';

        $loader->registerNamespace(
            'Shopware\\SitionSooqr',
            $pluginDir
        );
    }

    public function testCanCreateInstance()
    {
        /** @var Shopware_Plugins_Backend_SitionSooqr_Bootstrap $plugin */
        $plugin = Shopware()->Plugins()->Backend()->SitionSooqr();

        $this->assertInstanceOf('Shopware_Plugins_Backend_SitionSooqr_Bootstrap', $plugin);
    }
}