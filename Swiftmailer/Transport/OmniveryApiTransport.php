<?php

namespace MauticPlugin\OmniveryMailerBundle\Mailer\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Mautic\EmailBundle\Mailer\Transport\BounceProcessorInterface;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportInterface;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportTrait;
use Mautic\EmailBundle\Mailer\Transport\UnsubscriptionProcessorInterface;
use Mautic\EmailBundle\Mailer\Transport\TransportFactory;


/**
 * @author Matic Zagmajster
 */
class OmniveryApiTransport extends AbstractApiTransport implements BounceProcessorInterface, TokenTransportInterface, UnsubscriptionProcessorInterface
{
    private const HOST = 'mg-api.omnivery.net';

    private $key;
    private $domain;

    public function __construct(string $key, string $domain, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;
        $this->domain = $domain;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('mautic_omnivery+api://%s?domain=%s', $this->getEndpoint(), $this->domain);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $body = new FormDataPart($this->getPayload($email, $envelope));
        $headers = [];
        foreach ($body->getPreparedHeaders()->all() as $header) {
            $headers[] = $header->toString();
        }

        $endpoint = sprintf('%s/v3/%s/messages', $this->getEndpoint(), urlencode($this->domain));
        $response = $this->client->request('POST', 'https://'.$endpoint, [
            'auth_basic' => 'api:'.$this->key,
            'headers' => $headers,
            'body' => $body->bodyToIterable(),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Omnivery server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$result['message'].sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['id']);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $headers = $email->getHeaders();
        $html = $email->getHtmlBody();
        if (null !== $html && \is_resource($html)) {
            if (stream_get_meta_data($html)['seekable'] ?? false) {
                rewind($html);
            }
            $html = stream_get_contents($html);
        }
        [$attachments, $inlines, $html] = $this->prepareAttachments($email, $html);

        $payload = [
            'from' => $envelope->getSender()->toString(),
            'to' => implode(',', $this->stringifyAddresses($this->getRecipients($email, $envelope))),
            'subject' => $email->getSubject(),
            'attachment' => $attachments,
            'inline' => $inlines,
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

    private function prepareAttachments(Email $email, ?string $html): array
    {
        $attachments = $inlines = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                // replace the cid with just a file name (the only supported way by Omnivery)
                if ($html) {
                    $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
                    $new = basename($filename);
                    $html = str_replace('cid:'.$filename, 'cid:'.$new, $html);
                    $p = new \ReflectionProperty($attachment, 'filename');
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

    private function getEndpoint(): ?string
    {
        return $this->coreParametersHelper->get('mailer_omnivery_host', $this->host);
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



