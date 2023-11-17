<?php

namespace MauticPlugin\OmniveryMailerBundle\Mailer\Transport;

use Mautic\EmailBundle\Mailer\Transport\TokenTransportInterface;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportTrait;
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
        $this->host            = $host;
        $this->key             = $key;
        $this->domain          = $domain;
        $this->maxBatchLimit   = $maxBatchLimit;
        $this->callbackUrl     = $callbackUrl;

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

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $body    = new FormDataPart($this->getPayload($email, $envelope));
        $headers = [];
        foreach ($body->getPreparedHeaders()->all() as $header) {
            $headers[] = $header->toString();
        }

        $endpoint = sprintf('%s/v3/%s/messages', $this->getEndpoint(), urlencode($this->domain));
        $response = $this->client->request('POST', 'https://'.$endpoint, [
            'auth_basic'   => 'api:'.$this->key,
            'headers'      => $headers,
            'body'         => $body->bodyToIterable(),
        ]);

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

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $headers = $email->getHeaders();
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

    private function prepareAttachments(Email $email, ?string $html): array
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

    private function getEndpoint(): ?string
    {
        return $this->host;
    }

    // Mautic stuff...
    public function getMaxBatchLimit(): int
    {
        return $this->maxBatchLimit;
    }
}
