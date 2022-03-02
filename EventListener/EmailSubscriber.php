<?php

namespace MauticPlugin\OmniveryMailerBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailEvent;
use Mautic\EmailBundle\Model\EmailModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmailSubscriber implements EventSubscriberInterface
{
    private $model;

    public function __construct(EmailModel $model)
    {
        $this->model            = $model;
    }

    public static function getSubscribedEvents()
    {
        return [
            EmailEvents::EMAIL_POST_SAVE => [
                ['addGroupIdHeader', 0],
            ],
        ];
    }

    public function addGroupIdHeader(EmailEvent $event)
    {
        $email   = $event->getEmail();
        $headers = $email->getHeaders();
        $groupId = null;
        if (isset($headers['OMNIVERYGROUPID'])) {
            $groupId = \filter_var($headers['OMNIVERYGROUPID'], \FILTER_VALIDATE_INT);
        }

        if ($groupId === $email->getId()) {
            return;
        }

        $headers['OMNIVERYGROUPID'] = $email->getId();
        $email->setHeaders($headers);
        $this->model->saveEntity($email);
    }
}
