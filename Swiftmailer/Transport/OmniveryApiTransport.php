<?php

namespace MauticPlugin\OmniveryMailerBundle\Swiftmailer\Transport;

use GuzzleHttp\Client;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\EmailBundle\Swiftmailer\Transport\AbstractTokenArrayTransport;
use Mautic\EmailBundle\Swiftmailer\Transport\CallbackTransportInterface;
use Mautic\LeadBundle\Entity\DoNotContact;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Translation\TranslatorInterface;

class OmniveryApiTransport extends AbstractTokenArrayTransport implements \Swift_Transport, CallbackTransportInterface
{
    private $host = 'mg-api.omnivery.dev';

    /**
     * @var int
     */
    private $maxBatchLimit;
    /**
     * @var int|null
     */
    private $batchRecipientCount;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $apiKey;
    /**
     * @var string
     */
    private $domain;
    /**
     * @var string
     */
    private $region;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var TransportCallback
     */
    private $transportCallback;
    /**
     * @var null
     */
    private $webhookSigningKey;

    private $accountDomain;

    private $accountConfig;

    private $coreParametersHelper;

    private function setAccountConfig($email)
    {
        $email = strtolower($email);
        $parts = explode('@', $email);

        // $parts[1] should contain top level domain.
        $this->accountDomain = $parts[1];
        $this->accountConfig = $this->coreParametersHelper->get('mailer_mailgun_accounts');

        if (isset($this->accountConfig[$this->accountDomain])) {
            $this->accountConfig = $this->accountConfig[$this->accountDomain];
        } else {
            // Config not found.
            $this->accountDomain = null;
            $this->accountConfig = [];
        }

        return $this;
    }

    private function isAccountConfigLoaded()
    {
        return null !== $this->accountDomain;
    }

    public function __construct(TransportCallback $transportCallback, Client $client, TranslatorInterface $translator, int $maxBatchLimit, ?int $batchRecipientCount, $webhookSigningKey = '', LoggerInterface $logger, CoreParametersHelper $coreParametersHelper)
    {
        $this->transportCallback    = $transportCallback;
        $this->client               = $client;
        $this->translator           = $translator;
        $this->maxBatchLimit        = $maxBatchLimit;
        $this->batchRecipientCount  = $batchRecipientCount ?: 0;
        $this->webhookSigningKey    = $webhookSigningKey;
        $this->accountDomain        = null;
        $this->accountConfig        = [];
        $this->logger               = $logger;
        $this->coreParametersHelper = $coreParametersHelper;
    }

    public function setApiKey(?string $apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getApiKey(): string
    {
        if (null !== $this->accountDomain) {
            return $this->accountConfig['api_key'];
        }

        // Use value from Email Settings.
        return $this->apiKey;
    }

    public function setDomain(?string $domain)
    {
        $this->domain = $domain;

        return $this;
    }

    public function getDomain(): string
    {
        if (null !== $this->accountDomain) {
            return $this->accountConfig['host'];
        }

        // Use value from Email Settings.
        return $this->domain;
    }

    public function setRegion(?string $region)
    {
        $this->region = $region;

        return $this;
    }

    public function getRegion(): string
    {
        if (null !== $this->accountDomain) {
            return $this->accountConfig['region'];
        }

        return $this->region;
    }

    public function start(): void
    {
        if (empty($this->getApiKey())) {
            $this->throwException($this->translator->trans('mautic.email.api_key_required', [], 'validators'));
        }

        $this->started = true;
    }

    /**
     * @param null $failedRecipients
     *
     * @return int
     *
     * @throws \Exception
     */
    public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $count            = 0;
        $failedRecipients = (array) $failedRecipients;

        if ($evt = $this->getDispatcher()->createSendEvent($this, $message)) {
            $this->getDispatcher()->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled()) {
                return 0;
            }
        }

        /**
         * @todo implement multi domain feature
         */
        // Fully initialize instance to use Omnivery-multi account feature.

        if (!$this->isAccountConfigLoaded()) {
            /**
             * @todo
             */
        }

        try {
            $count = $this->getBatchRecipientCount($message);

            $preparedMessage = $this->getMessage($message);
            $preparedMessage = $this->setAdditionalMessageAttributes($preparedMessage);

            $payload                      = $this->getPayload($preparedMessage);
            if (isset($preparedMessage['headers'])) {
                foreach ($preparedMessage['headers'] as $key => $value) {
                    $headerKey           = 'h:'.$key;
                    $payload[$headerKey] = $value;
                }
            }

            $endpoint = sprintf('%s/v3/%s/messages', $this->getEndpoint(), urlencode($this->getDomain()));

            $this->logger->debug('Request endpoint: '.$endpoint);
            $this->logger->debug('Request: '.serialize($payload));
            $response = $this->client->post(
                'https://'.$endpoint,
                [
                    'auth'        => ['api', $this->getApiKey(), 'basic'],
                    'form_params' => $payload,
                ]
            );

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                if ('application/json' === $response->getHeaders(false)['content-type'][0]) {
                    $result = $response->toArray(false);
                    throw new \Swift_TransportException('Unable to send an email: '.$result['message'].sprintf(' (code %d).', $response->getStatusCode()), $response);
                }

                throw new \Swift_TransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $response->getStatusCode()), $response);
            }

            if ($evt) {
                $evt->setResult(\Swift_Events_SendEvent::RESULT_SUCCESS);
                $evt->setFailedRecipients($failedRecipients);
                $this->getDispatcher()->dispatchEvent($evt, 'sendPerformed');
            }

            return $count;
        } catch (\Exception $e) {
            $this->triggerSendError($evt, $failedRecipients);
            $message->generateId();
            $this->throwException($e->getMessage());
        }

        return $count;
    }

    /**
     * Return the max number of to addresses allowed per batch.  If there is no limit, return 0.
     *
     * @see https://help.mailgun.com/hc/en-us/articles/203068914-What-Are-the-Differences-Between-the-Free-and-Flex-Plans-
     *      there is limit depending on your account, and you can change it in configuration for this plugin
     *      Free plan requires 300 messages per day
     */
    public function getMaxBatchLimit(): int
    {
        return $this->maxBatchLimit;
    }

    /**
     * Get the count for the max number of recipients per batch.
     *
     * @see https://help.mailgun.com/hc/en-us/articles/203068914-What-Are-the-Differences-Between-the-Free-and-Flex-Plans-
     *      5 Authorized Recipients for free plan and no limit for Flex Plan
     *
     * @param int    $toBeAdded Number of emails about to be added
     * @param string $type      Type of emails being added (to, cc, bcc)
     */
    public function getBatchRecipientCount(\Swift_Message $message, $toBeAdded = 1, $type = 'to'): int
    {
        $toCount  = is_array($message->getTo()) ? count($message->getTo()) : 0;
        $ccCount  = is_array($message->getCc()) ? count($message->getCc()) : 0;
        $bccCount = is_array($message->getBcc()) ? count($message->getBcc()) : 0;

        return null === $this->batchRecipientCount ? $this->batchRecipientCount : $toCount + $ccCount + $bccCount + $toBeAdded;
    }

    /**
     * Returns a "transport" string to match the URL path /mailer/{transport}/callback.
     */
    public function getCallbackPath(): string
    {
        return 'omnivery_api';
    }

    public function getWhCallbackUrl()
    {
        return sprintf(
            '%s/mailer/%s/callback',
            $this->coreParametersHelper->get('site_url'),
            $this->getCallbackPath()
        );
    }

    /**
     * Handle response.
     *
     * @preplaced
     *
     * @return mixed
     */
    public function processCallbackRequest(Request $request)
    {
        $this->logger->debug('Start processCallbackRequest');
        $postData = json_decode($request->getContent(), true);

        if (is_array($postData) && isset($postData['event_data'])) {
            // Mailgun API callback
            $events = [
                $postData['event_data'],
            ];
        } else {
            // response must be an array
            return null;
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

            if ('bounce' === $event['event'] || 'rejected' === $event['event'] || 'permanent_fail' === $event['event'] || 'failed' === $event['event']) {
                $type = DoNotContact::BOUNCED;
            } elseif ('complained' === $event['event']) {
                $type = DoNotContact::UNSUBSCRIBED;
            } elseif ('unsubscribed' === $event['event']) {
                $reason = 'User unsubscribed';
                $type   = DoNotContact::UNSUBSCRIBED;
            } else {
                continue;
            }

            $channelId = null;
            $this->logger->debug(serialize($event));
            if (isset($event['message']['headers'], $event['message']['headers'])) {
                $event['CustomID'] = $event['message']['headers']['X-EMAIL-ID'];

                // Make sure channel ID is always set, so data on graph is displayed correctly.
                $channelId = (int) $event['CustomID'];
            }

            if (isset($event['CustomID']) && '' !== $event['CustomID'] && false !== strpos($event['CustomID'], '-', 0)) {
                $fistDashPos = strpos($event['CustomID'], '-', 0);
                $leadIdHash  = substr($event['CustomID'], 0, $fistDashPos);
                $leadEmail   = substr($event['CustomID'], $fistDashPos + 1, strlen($event['CustomID']));
                if ($event['recipient'] == $leadEmail) {
                    $this->transportCallback->addFailureByHashId($leadIdHash, $reason, $type);
                }
            } else {
                $this->transportCallback->addFailureByAddress($event['recipient'], $reason, $type, $channelId);
            }
        }

        $this->logger->debug('End processCallbackRequest');
    }

    /**
     * @param array $failedRecipients
     */
    private function triggerSendError(\Swift_Events_SendEvent $evt, &$failedRecipients): void
    {
        $failedRecipients = array_merge(
            $failedRecipients,
            array_keys((array) $this->message->getTo()),
            array_keys((array) $this->message->getCc()),
            array_keys((array) $this->message->getBcc())
        );

        if ($evt) {
            $evt->setResult(\Swift_Events_SendEvent::RESULT_FAILED);
            $evt->setFailedRecipients($failedRecipients);
            $this->getDispatcher()->dispatchEvent($evt, 'sendPerformed');
        }
    }

    private function getEndpoint(): string
    {
        //return str_replace('%region_dot%', 'us' !== ($this->getRegion() ?: 'us') ? $this->getRegion().'.' : '', $this->host);
        return $this->host;
    }

    private function getMessage($message): array
    {
        $this->message = $message;
        $metadata      = $this->getMetadata();

        $mauticTokens = $tokenReplace = $mailgunTokens = [];
        if (!empty($metadata)) {
            $metadataSet  = reset($metadata);
            $tokens       = (!empty($metadataSet['tokens'])) ? $metadataSet['tokens'] : [];
            $mauticTokens = array_keys($tokens);
            foreach ($tokens as $search => $token) {
                $tokenKey               = preg_replace('/[^\da-z]/i', '_', trim($search, '{}'));
                $tokenReplace[$search]  = '%recipient.'.$tokenKey.'%';
                $mailgunTokens[$search] = $tokenKey;
            }
        }

        $messageArray = $this->messageToArray($mauticTokens, $tokenReplace, true);

        $messageArray['recipient-variables'] = [];
        $messageArray['to']                  = [];
        $recipients                          = [];
        foreach ($metadata as $recipient => $mailData) {
            $recipients[]                                    = $recipient;
            $messageArray['recipient-variables'][$recipient] = [];
            foreach ($mailData['tokens'] as $token => $tokenData) {
                $messageArray['recipient-variables'][$recipient][$mailgunTokens[$token]] = $tokenData;
            }
        }

        if (!count($recipients)) {
            $recipients = array_keys($messageArray['recipients']['to']);
        }

        $messageArray['to'] = implode(',', $recipients);

        return $messageArray;
    }

    private function setAdditionalMessageAttributes($preparedMessage)
    {
        $from          = $this->message->getFrom();
        $fromEmail     = current(array_keys($from));
        $currentName   = $from[$fromEmail];
        // From name, email
        if (!isset($preparedMessage['from']) || !is_array($preparedMessage['from'])) {
            $preparedMessage['from'] = [];
        }

        $defaultFromEmail = $this->coreParametersHelper->get('mailer_from_email');
        $defaultFromName  = $this->coreParametersHelper->get('mailer_from_name');
        if (!isset($preparedMessage['from']['name'])) {
            $preparedMessage['from']['name'] = (!empty($currentName)) ? $currentName : $defaultFromName;
        }

        if (isset($preparedMessage['from']['email'])) {
            $preparedMessage['from']['email'] = (!empty($fromEmail)) ? $fromEmail : $defaultFromEmail;
        }

        if (!isset($preparedMessage['headers'])) {
            $preparedMessage['headers'] = [];
        }

        // List Unsubscribe Header
        $recipientVars = $preparedMessage['recipient-variables'];
        $leadEmail     = null;
        $leadData      = [];
        if (count($recipientVars)) {
            $leadEmail = array_keys($recipientVars)[0];
            $leadData  = $recipientVars[$leadEmail];
        }

        if (isset($leadData['unsubscribe_url'])) {
            $preparedMessage['headers']['List-Unsubscribe'] = $leadData['unsubscribe_url'];
        }

        return $preparedMessage;
    }

    private function getPayload(array $message): array
    {
        $data    = $this->message->getTo();
        $toEmail = current(array_keys($data));
        $payload = [
            'from'    => sprintf('"%s" <%s>', $message['from']['name'], $message['from']['email']),
            'to'      => sprintf('"%s" <%s>', $data[$toEmail], $toEmail),
            'subject' => $message['subject'],
            'html'    => $message['html'],
            'text'    => $message['text'],
        ];

        // Configrue all webhooks automatically for the end user.
        if (MAUTIC_ENV == 'prod') {
            $payload['callback_url'] = $this->getWhCallbackUrl();
        }

        $data = $this->message->getReplyTo();
        if (is_array($data) && count($data)) {
            $payload['repy_to'] = implode(',', $data);
        }

        $data = $this->message->getBcc();
        if (is_array($data) && count($data)) {
            $payload['bcc'] = implode(',', $data);
        }

        $data = $this->message->getCc();
        if (is_array($data) && count($data)) {
            $payload['cc'] = implode(',', $data);
        }

        if (count($message['recipient-variables'])) {
            $payload['recipient-variables'] = json_encode($message['recipient-variables']);
        }
        $this->logger->notice('recipient-variables');
        $this->logger->notice(json_encode($message['recipient-variables']));

        return $payload;
    }

    public function verifyCallback(string $token, string $timestamp, string $signature): bool
    {
        // check if the timestamp is fresh
        if (\abs(\time() - $timestamp) > 15) {
            return false;
        }

        // returns true if signature is valid
        return \hash_equals(\hash_hmac('sha256', $timestamp.$token, $this->webhookSigningKey), $signature);
    }
}
