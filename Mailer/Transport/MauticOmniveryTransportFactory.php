<?php

namespace MauticPlugin\OmniveryMailerBundle\Mailer\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Matic Zagmajster <maticzagmajster@gmail.com>
 */
final class MauticOmniveryTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        EventDispatcherInterface $dispatcher = null,
        HttpClientInterface $client = null,
        LoggerInterface $logger = null
    ) {
        parent::__construct(
            $dispatcher,
            $client,
            $logger
        );
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('mautic+omnivery+api' === $scheme) {
            $host          = ('default' === $dsn->getHost()) ? OmniveryApiTransport::HOST : $dsn->getHost();
            $key           =  $dsn->getPassword();
            $domain        = $dsn->getOption('domain');
            $maxBatchLimit = $dsn->getOption('maxBatchLimit') ?? 5000;

            if (null === $key || null === $domain) {
                throw new InvalidArgumentException('Key or domain not set, cannot create OmniveryApiTransport object!');
            }

            return new OmniveryApiTransport(
                $host,
                $key,
                $domain,
                $maxBatchLimit,
                $this->dispatcher,
                $this->client,
                $this->logger
            );
        }

        throw new UnsupportedSchemeException($dsn, 'omnivery', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mautic+omnivery+api'];
    }
}
