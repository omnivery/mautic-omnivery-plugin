<?php

namespace MauticPlugin\OmniveryMailerBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\TransportWebhookEvent;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * Supported webhook events by Omnivery.
 *
 * evevnt              O M
 * delivered           * *
 * failed              * x
 * rejected            * x
 * opened              * *
 * clicked             * *
 * unsubscribed        * *
 * complained          * *
 * accepted            x *
 * permanent_fail      x *
 * temporary_fail      x *
 *
 * More info here: https://docs.omnivery.com/docs/mailgun-api-v3/5cc9374300b99-webhooks#webhook-event-types
 */
class CallbackSubscriber implements EventSubscriberInterface
{
    public const WEBHOOK_MESSAGE_EVENTS = [
        'delivered',
        'failed',
        'rejected',
        'opened',
        'clicked',
        'unsubscribed',
        'complained',
    ];

    public function __construct(
        private TransportCallback $transportCallback,
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger
    ) {
    }

    private function getEmailChannelId($headers): string
    {
        $keys = array_keys($headers);
        foreach ($keys as $index => $orgKeyName) {
            if ('x-email-id' == strtolower($orgKeyName)) {
                return (string) $headers[$orgKeyName];
            }
        }

        return '';
    }

    private function isValidMessageEvent(array $eventData): bool
    {
        return in_array($eventData['event'], self::WEBHOOK_MESSAGE_EVENTS);
    }

    private function processCallbackByEmailAddress(?string $recipient, array $eventData): void
    {
        $event          = $eventData['event'];
        $deliveryStatus = $eventData['delivery-status'] ?? [];
        $messageHeaders = $eventData['message']['headers'] ?? [];
        $severity       = $eventData['severity'] ?? null;
        $channelId      = $this->getEmailChannelId($messageHeaders);

        if (isset($deliveryStatus['description'])) {
            $comments = $deliveryStatus['description'];
        } else {
            $comments = $deliveryStatus['message'];
        }

        $type            = null;  // reason in database
        $canUseChannelId = true;
        switch ($event) {
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
                $comments = 'unsubscribed';
                $type     = DoNotContact::UNSUBSCRIBED;
                break;

            default:
                break;
        }

        if (null === $type) {
            // It does not appear that there is anyhing wrong
            // with the message. Nothing else to do here :).
            return;
        }

        if (null !== $channelId && $canUseChannelId) {
            $this->transportCallback->addFailureByAddress(
                $recipient,
                $comments,
                $type,
                $channelId
            );
        } else {
            $this->transportCallback->addFailureByAddress(
                $event['recipient'],
                $comments,
                $type,
                null
            );
        }
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

        $postData  = json_decode($event->getRequest()->getContent(), true);
        $eventData = null;
        $this->logger->debug('Start processCallbackRequest, incomming request', ['postData' => $postData]);

        if (is_array($postData) && isset($postData['event-data'])) {
            // Omnivery API callback
            $eventData = $postData['event-data'];
        } else {
            $event->setResponse(
                new Response(
                    json_encode([
                        'message' => 'Could not find event-data key, not processing.',
                        'success' => false,
                    ]),
                    Response::HTTP_BAD_REQUEST,
                    ['content-type' => 'application/json']
                )
            );

            $this->logger->error(
                'OmniveryTransportCallbackSubscriber: Could not process webhook request for payload.',
                ['payload' => $postData]
            );

            return;
        }

        if (!$this->isValidMessageEvent($eventData)) {
            $event->setResponse(
                new Response(
                    json_encode([
                        'message' => sprintf('Unrecognized event type: "%s".', $eventData['event']),
                        'success' => false,
                    ]),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['content-type' => 'application/json']
                )
            );

            $this->logger->warning(
                'OmniveryTransportCallbackSubscriber: Unrecognized event type.',
                ['type' => $eventData['event']]
            );
        }

        $recipient = $eventData['recipient'] ?? null;
        $this->processCallbackByEmailAddress($recipient, $eventData);

        $event->setResponse(
            new Response(
                json_encode([
                    'message' => 'OK',
                    'success' => true,
                ]),
                Response::HTTP_OK
            )
        );
        $this->logger->debug('End processCallbackRequest');
    }
}
