<?php

namespace Shopware\SitionSooqr\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\SitionSooqr\Components\Log;
use Shopware\SitionSooqr\Components\SooqrXml;

class Frontend implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            // 'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendPostDispatch',

            // cron job
            'Shopware_CronJob_SitionSooqrBuildXml'   => 'onRunSitionSooqrBuildXml',
        ];
    }

    public function onFrontendPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var $controller \Enlight_Controller_Action */
        $controller = $args->getSubject();
        $view = $controller->View();
    }

    public function onRunSitionSooqrBuildXml(\Shopware_Components_Cron_CronJob $job)
    {
        $db = Shopware()->Db();
        $shopIds = array_map(function($row) { return $row; }, $db->executeQuery('SELECT id FROM s_core_shops')->fetchAll());

        while(count($shopIds) > 0)
        {
            set_time_limit(60 * 60); // 1 hour

            // get a random shop
            $key = array_rand($shopIds);
            $shopId = $shopIds[$key];

            // remove key from array
            unset($shopIds[$key]);
            array_values($shopIds);


            $sooqr = new SooqrXml($shopId);

            $maxSeconds = 3 * 60 * 60; // xml can be 3 hours old max

            if( $sooqr->needBuilding($maxSeconds) )
            {
                $echoOutput = false;
                $sooqr->buildXml($echoOutput);

                // just generate for 1 shop the xml and return
                return;
            }
        }
    }
}
