<?php

namespace MauticPlugin\OmniveryMailerBundle\Mailer\Transport;

use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportInterface;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportTrait;
use MauticPlugin\OmniveryMailerBundle\DevTools;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Matic Zagmajster
 */
class OmniveryApiTransport extends AbstractApiTransport implements TokenTransportInterface
{
    use TokenTransportTrait;

    public const HOST = 'mg-api.omnivery.net';

    /**
     * @var LoggerInterface
     */
    private $logger;

    // Configuration

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $domain;

    /**
     * @var int
     */
    private $maxBatchLimit;

    /**
     * @var string
     */
    private $callbackUrl;

    /**
     * @var array
     */
    private $mauticTransportOptions;

    public function __construct(
        string $host = '',
        string $key = '',
        string $domain = '',
        int $maxBatchLimit = 0,
        string $callbackUrl = '',
        EventDispatcherInterface $dispatcher = null,
        HttpClientInterface $client = null,
        LoggerInterface $logger = null,
    ) {
        $this->host                   = $host;
        $this->key                    = $key;
        $this->domain                 = $domain;
        $this->maxBatchLimit          = $maxBatchLimit;
        $this->callbackUrl            = $callbackUrl;
        $this->mauticTransportOptions = [
            'o:testmode' => 'no',
            'o:tracking' => 'yes',
        ];

        $this->logger          = $logger;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf(
            'mautic+omnivery+api://%s?domain=%s',
            $this->getEndpoint(),
            $this->domain
        );
    }

    private function getEndpoint(): ?string
    {
        return $this->host;
    }

    public function getMaxBatchLimit(): int
    {
        return $this->maxBatchLimit;
    }

    private function prepareAttachments(MauticMessage $email, ?string $html): array
    {
        $attachments = $inlines = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                // replace the cid with just a file name (the only supported way by Omnivery)
                if ($html) {
                    $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
                    $new      = basename($filename);
                    $html     = str_replace('cid:'.$filename, 'cid:'.$new, $html);
                    $p        = new \ReflectionProperty($attachment, 'filename');
                    $p->setAccessible(true);
                    $p->setValue($attachment, $new);
                }
                $inlines[] = $attachment;
            } else {
                $attachments[] = $attachment;
            }
        }

        return [$attachments, $inlines, $html];
    }

    private function getPayloadOld(SentMessage $sentMessage, $envelope): array
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        $metadata = $email->getMetadata();
        DevTools::debugLog('Metadata '.json_encode($metadata));

        $toArray     = $email->getTo();
        $simpleArray = [];
        foreach ($toArray as $obj) {
            $simpleArray[] = $obj->getAddress();
        }

        DevTools::debugLog('What...');
        DevTools::debugLog(implode(',', $simpleArray));

        $headers = $email->getHeaders();
        DevTools::debugLog('payloadHeaders ', json_encode($headers->all()));
        $html    = $email->getHtmlBody();
        if (null !== $html && \is_resource($html)) {
            if (stream_get_meta_data($html)['seekable'] ?? false) {
                rewind($html);
            }
            $html = stream_get_contents($html);
        }
        [$attachments, $inlines, $html] = $this->prepareAttachments($email, $html);

        $payload = [
            'from'         => $envelope->getSender()->toString(),
            'to'           => implode(',', $this->stringifyAddresses($this->getRecipients($email, $envelope))),
            'subject'      => $email->getSubject(),
            'attachment'   => $attachments,
            'inline'       => $inlines,
            'callback_url' => $this->callbackUrl,
        ];
        if ($emails = $email->getCc()) {
            $payload['cc'] = implode(',', $this->stringifyAddresses($emails));
        }
        if ($emails = $email->getBcc()) {
            $payload['bcc'] = implode(',', $this->stringifyAddresses($emails));
        }
        if ($email->getTextBody()) {
            $payload['text'] = $email->getTextBody();
        }
        if ($html) {
            $payload['html'] = $html;
        }

        $headersToBypass = ['from', 'to', 'cc', 'bcc', 'subject', 'content-type'];
        foreach ($headers->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $payload['o:tag'] = $header->getValue();

                continue;
            }

            if ($header instanceof MetadataHeader) {
                $payload['v:'.$header->getKey()] = $header->getValue();

                continue;
            }

            // Check if it is a valid prefix or header name according to Omnivery API
            $prefix = substr($name, 0, 2);
            if (\in_array($prefix, ['h:', 't:', 'o:', 'v:']) || \in_array($name, ['recipient-variables', 'template', 'amp-html'])) {
                $headerName = $header->getName();
            } else {
                $headerName = 'h:'.$header->getName();
            }

            $payload[$headerName] = $header->getBodyAsString();
        }

        return $payload;
    }

    private function mauticStringifyAddresses(array $addresses): string
    {
        if (!count($addresses)) {
            return '';
        }

        $stringAddresses = [];
        foreach ($addresses as $address) {
            $stringAddresses[] = $address->toString();
        }

        return implode(',', $stringAddresses);
    }

    private function mauticGetRecipientData(SentMessage $sentMessage): \Generator
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        $metadata = $email->getMetadata();
        foreach ($metadata as $email => $meta) {
            yield [
                'emailTo' => $email,
                'meta'    => $meta,
            ];
        }
    }

    private function mauticGetPayload(SentMessage $sentMessage, array $recipientMeta): array
    {
        $email = $sentMessage->getOriginalMessage();

        if (!$email instanceof MauticMessage) {
            throw new TransportException('Message must be an instance of '.MauticMessage::class);
        }

        // Work with objects so we can use mauticStringifyAddresses to properly format.
        $recipientName = $recipientMeta['meta']['name'] ?? '';
        $addressTo     = new Address(
            $recipientMeta['emailTo'],
            $recipientName
        );
        $text    = $email->getTextBody();
        $html    = $email->getHtmlBody();
        $headers = $email->getHeaders();

        // This headers can be overwritten if they are specified with the email.

        // Details on how to behave with message.
        $oHeaders = [
            'o:testmode' => $this->mauticTransportOptions['o:testmode'],
            'o:tracking' => $this->mauticTransportOptions['o:tracking'],
        ];

        // Attach custom JSON data.
        $vHeaders = [];

        // Template variables.
        $tHeaders = [];

        // Other headers.
        $hHeaders = [];

        /**
         * @todo Test attachment sending.
         */
        [$attachments, $inlines, $html] = $this->prepareAttachments($email, $html);

        // We ignore these headers since we set them explicitly at the end.
        $headersToBypass = [
            'from',
            'to',
            'reply-to',
            'cc',
            'bcc',
            'subject',
            'content-type',
        ];

        foreach ($headers->all() as $name => $header) {
            if (\in_array(strtolower($name), $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $oHeaders['o:tag'] = $header->getValue();
                continue;
            }

            if ($header instanceof MetadataHeader) {
                $vHeaderKey            ='v:'.$header->getKey();
                $vHeaders[$vHeaderKey] = $header->getValue();
                continue;
            }

            // Check if it is a valid prefix or header name according to Omnivery API
            $prefix = substr($name, 0, 2);
            switch ($prefix) {
                case 'o:':
                    $oHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 'v:':
                    $vHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 't:':
                    $tHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                case 'h:':
                    $hHeaders[$header->getName()] = $header->getBodyAsString();
                    break;
                default:
                    $headerName            = 'h:'.$header->getName();
                    $hHeaders[$headerName] = $header->getBodyAsString();
            }
        }

        $substitutions = $recipientMeta['meta']['tokens'] ?? [];

        return array_merge(
            [
                'from'          => $this->mauticStringifyAddresses($email->getFrom()),
                'to'            => $this->mauticStringifyAddresses([$addressTo]),
                'reply_to'      => $this->mauticStringifyAddresses($email->getReplyTo()),
                'cc'            => $this->mauticStringifyAddresses($email->getCc()),
                'bcc'           => $this->mauticStringifyAddresses($email->getBcc()),
                'subject'       => $email->getSubject(),
                'text'          => $text,
                'html'          => $html,
                'attachment'    => $attachments,
                'inline'        => $inlines,
                'substitutions' => json_encode($substitutions),
                'callback_url'  => $this->callbackUrl,
            ],
            $oHeaders,
            $vHeaders,
            $tHeaders,
            $hHeaders,
        );
    }

    private function mauticGetApiResponse(array $payload): ResponseInterface
    {
        $endpoint = sprintf(
            '%s/v3/%s/messages',
            $this->getEndpoint(),
            urlencode($this->domain)
        );

        return $this->client->request(
            'POST',
            'https://'.$endpoint,
            [
                'auth_basic'   => 'api:'.$this->key,
                'headers'      => ['Content-Type: application/x-www-form-urlencoded'],
                'body'         => $payload,
            ]
        );
    }

    private function handleError(ResponseInterface $response): void
    {
        if (200 === $response->getStatusCode()) {
            return;
        }

        $data = json_decode($response->getContent(false), true);
        $this->logger->error('OmniveryApiTransport error response', $data);

        throw new HttpTransportException('Error returned by API', $response, $response->getStatusCode());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        try {
            $recipientsMeta = $this->mauticGetRecipientData($sentMessage);
            foreach ($recipientsMeta as $recipientMeta) {
                /*DevTools::debugLog('recipientMeta ');
                DevTools::debugLog(print_r($recipientMeta, true));*/
                $payload = $this->mauticGetPayload(
                    $sentMessage,
                    $recipientMeta
                );
                DevTools::debugLog('Payload to send: ');
                DevTools::debugLog(print_r($payload, true));

                /**
                 * @todo implement ?
                 */
                // $this->checkTemplateIsValid($payload);

                $response = $this->mauticGetApiResponse($payload);
                $this->handleError($response);

                /**
                 * @todo implement ?
                 */
                // if ($errorMessage = $this->getErrorMessageFromResponseBody($response->toArray())) {
                //     /** @var MauticMessage $message */
                //     $message = $sentMessage->getOriginalMessage();
                //     $this->processImmediateSendFeedback($payload, $response->toArray(), $message->getMetadata());
                //     throw new TransportException($errorMessage);
                // }
            }

            return $response;
        } catch (\Exception $e) {
            throw new TransportException($e->getMessage());
        }

        /*

        $body    = new FormDataPart($this->getPayload($sentMessage));
        $headers = [];
        foreach ($body->getPreparedHeaders()->all() as $header) {
            $headers[] = $header->toString();
        }

        try {
            $statusCode = $response->getStatusCode();
            $result     = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Omnivery server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$result['message'].sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['id']);

        return $response;*/
    }
}
