<?php

namespace MauticPlugin\OmniveryMailerBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\TransportWebhookEvent;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CallbackSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TransportCallback $transportCallback,
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::ON_TRANSPORT_WEBHOOK => ['processCallbackRequest', 0],
        ];
    }

    public function processCallbackRequest(TransportWebhookEvent $event): void
    {
        $dsn = Dsn::fromString($this->coreParametersHelper->get('mailer_dsn'));

        if ('mautic+omnivery+api' !== $dsn->getScheme()) {
            return;
        }

        $this->logger->debug('Start processCallbackRequest');
        $postData = json_decode($event->getRequest()->getContent(), true);

        if (is_array($postData) && isset($postData['event_data'])) {
            // Omnivery API callback
            $events = [
                $postData['event_data'],
            ];
        } else {
            // Response must be an array.
            return;
        }

        foreach ($events as $event) {
            $this->logger->debug('Event '.$event['event']);
            if (!in_array($event['event'], ['bounce', 'rejected', 'complained', 'unsubscribed', 'permanent_fail', 'failed'])) {
                continue;
            }

            // Try to get a description of an error.
            $reason = $event['event'];
            if (isset($event['delivery-status'], $event['delivery-status']['message'], $event['delivery-status']['message'][0])) {
                $reason = $event['delivery-status']['message'][0];
            } elseif (isset($event['delivery-status'], $event['delivery-status']['description'])) {
                $reason = $event['delivery-status']['description'];
            }

            // Get error severity.
            $severity = null;
            if (isset($event['severity'])) {
                $severity = $event['severity'];
            }

            $type            = null;
            $canUseChannelId = true;
            switch ($event['event']) {
                case 'bounce':
                case 'permanent_fail':
                    $type = DoNotContact::BOUNCED;
                    break;

                case 'failed':
                    switch ($severity) {
                        case 'permanent':
                            $type = DoNotContact::BOUNCED;
                            break;

                        case 'temporary':
                        case 'softbounce':
                            $type            = DoNotContact::BOUNCED;
                            $canUseChannelId = false;
                            break;

                        default:
                            break;
                    }
                    break;

                case 'rejected':
                    $type            = DoNotContact::IS_CONTACTABLE;
                    $canUseChannelId = false;
                    break;

                case 'complained':
                    $type = DoNotContact::UNSUBSCRIBED;
                    break;

                case 'unsubscribed':
                    $reason = 'User unsubscribed';
                    $type   = DoNotContact::UNSUBSCRIBED;
                    break;

                default:
                    // Ignore any other events.
                    break;
            }

            $channelId = null;
            $this->logger->debug(serialize($event));
            if (isset($event['message']['headers'])) {
                $event['CustomID'] = $this->getEmailChannelId($event['message']['headers']);

                // Make sure channel ID is always set, so data on graph is displayed correctly.
                if (!empty($event['CustomID'])) {
                    $channelId = (int) $event['CustomID'];
                }
            }

            if (null !== $channelId && $canUseChannelId) {
                $this->transportCallback->addFailureByAddress($event['recipient'], $reason, $type, $channelId);
            } else {
                $this->transportCallback->addFailureByAddress($event['recipient'], $reason, $type, null);
            }
        }

        $this->logger->debug('End processCallbackRequest');
    }
}
