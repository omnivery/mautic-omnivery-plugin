<?php

namespace MauticPlugin\OmniveryMailerBundle\Mailer\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
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
        /*$user = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $region = $dsn->getOption('region');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();*/

        if ('mauticomnivery+api' === $scheme) {
            return new OmniveryApiTransport(
                $this->dispatcher,
                $this->client,
                $this->logger
            );
        }

        throw new UnsupportedSchemeException($dsn, 'omnivery', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mauticomnivery+api'];
    }
}
