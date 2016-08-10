<?php

namespace Shopware\SitionSooqr\Subscriber;

use Enlight\Event\SubscriberInterface;

class Frontend implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            // 'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendPostDispatch',

            // cron job
            'Shopware_CronJob_SitionSooqrBuildXml'   => 'onRunSitionSooqrBuildXml'
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

    }
}
