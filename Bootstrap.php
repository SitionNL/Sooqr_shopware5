<?php

use Shopware\SitionSooqr\Components\ShopwareConfig;
use Shopware\SitionSooqr\Components\Log;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * The Bootstrap class is the main entry point of any shopware plugin.
 *
 * Short function reference
 * - install: Called a single time during (re)installation. Here you can trigger install-time actions like
 *   - creating the menu
 *   - creating attributes
 *   - creating database tables
 *   You need to return "true" or array('success' => true, 'invalidateCache' => array()) in order to let the installation
 *   be successfull
 *
 * - update: Triggered when the user updates the plugin. You will get passes the former version of the plugin as param
 *   In order to let the update be successful, return "true"
 *
 * - uninstall: Triggered when the plugin is reinstalled or uninstalled. Clean up your tables here.
 */
class Shopware_Plugins_Backend_SitionSooqr_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    public function getVersion() {
        $info = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR .'plugin.json'), true);
        if ($info) {
            return $info['currentVersion'];
        } else {
            throw new Exception('The plugin has an invalid version file.');
        }
    }

    public function getLabel()
    {
        return 'SitionSooqr';
    }

    public function uninstall()
    {
        return true;
    }

    public function update($oldVersion)
    {
        return true;
    }

    public function install()
    {
        if (!$this->assertMinimumVersion('4.3.0')) {
            throw new \RuntimeException('At least Shopware 4.3.0 is required');
        }

        (new ShopwareConfig($this->config))->createConfig($this);

        $this->subscribeEvent(
            'Enlight_Controller_Front_DispatchLoopStartup',
            'onStartDispatch'
        );

        // register controller
        $this->registerController('frontend', 'SitionSooqr');

        $this->subscribeEvent(
            "Enlight_Controller_Action_PostDispatchSecure_Frontend",
            "onSecurePostDispatch"
        );

        $this->addConsoleCommand();

        return true;
    }

    public function onSecurePostDispatch(Enlight_Event_EventArgs $arguments)
    {
        /**
         * @var Enlight_Controller_Request_RequestHttp
         * engine/Library/Enlight/Controller/Request/RequestHttp.php
         */
        $request = $arguments->getRequest();

        $controllerName = $request->getControllerName();
        $actionName = $request->getActionName();

        $config = new ShopwareConfig(Shopware()->Config());

        if( in_array(trim($config->get('add_client_side_script')), [ "yes", "1", "true", "ja" ]) )
        {
            $sooqrAccountId = trim($config->get('account_identifier'));
            $jsSnippets = $config->get('options_client_side_script');

            $controller = $arguments->getSubject();
            $controller->View()->addTemplateDir($this->Path() . 'Views/');
            $controller->View()->extendsTemplate('frontend/index/search.tpl');
            $controller->View()->assign('sooqrAccountId', $sooqrAccountId);
            $controller->View()->assign('jsSnippets', $jsSnippets);
        }

        /**@var $controller Shopware_Controllers_Frontend_Listing*/
        // $controller = $arguments->getSubject();

        // $controller->View()->addTemplateDir($this->Path() . 'Views/common/');

        // if (Shopware()->Shop()->getTemplate()->getVersion() >= 3)
        // {
        //     $controller->View()->addTemplateDir($this->Path() . 'Views/responsive/');
        // } 
        // else 
        // {
        //     $controller->View()->addTemplateDir($this->Path() . 'Views/emotion/');
        //     $controller->View()->extendsTemplate('frontend/index/sooqr-search.tpl');
        // }
    }

    public function afterInit()
    {
        $this->registerMyComponents();   
    }

    /**
     * This callback function is triggered at the very beginning of the dispatch process and allows
     * us to register additional events on the fly. This way you won't ever need to reinstall you
     * plugin for new events - any event and hook can simply be registerend in the event subscribers
     */
    public function onStartDispatch(Enlight_Event_EventArgs $args)
    {
        $this->registerMyComponents();
                
        $subscribers = array(
            new \Shopware\SitionSooqr\Subscriber\Frontend()
        );

        foreach ($subscribers as $subscriber) {
            $this->Application()->Events()->addSubscriber($subscriber);
        }
    }

    public function registerMyComponents()
    {
        $this->Application()->Loader()->registerNamespace(
            'Shopware\SitionSooqr',
            $this->Path()
        );
    }

    /**
     * Register cron jobs needed for csv import & order export
     */
    public function initCronJobs() 
    {
        // http://community.shopware.com/Shopware-4-essentials-of-plugin-development_detail_1280.html#Registering_a_cronjob
        $this->createCronJob(
            'Sition Sooqr Build Xml',      // cron job name
            'Shopware_CronJob_SitionSooqrBuildXml', // event name
            60 * 60,                            // interval (seconds)
            true                                // active
        );

        // handling of cronjobs in Subscriber/EventsHandler.php
    }

    /**
     * Add console commands
     */
    public function addConsoleCommand()
    {
        $this->subscribeEvent(
            'Shopware_Console_Add_Command',
            'onAddConsoleCommand'
        );
    }

    public function onAddConsoleCommand($args)
    {
        return new ArrayCollection([
            new \Shopware\SitionSooqr\Commands\GenerateXmlCommand(),
        ]);
    }
}
