<?php

return [
    'name'        => 'OmniveryMailer',
    'description' => 'Integrate Mailer transport for Omnivery API',
    'author'      => 'Matic Zagmajster',
    'version'     => '1.0.2',

    'services' => [
        'forms' => [
            'mautic.omnivery.form.type.account' => [
                'class'     => \MauticPlugin\OmniveryMailerBundle\Form\Type\OmniveryAccountType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],

            'mautic.omnivery.form.type.config' => [
                'class'     => \MauticPlugin\OmniveryMailerBundle\Form\Type\ConfigType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],

        'events' => [
            'mautic.omnivery.subscriber.config' => [
                'class'     => \MauticPlugin\OmniveryMailerBundle\EventListener\ConfigSubscriber::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],

        'integrations' => [],

        'other' => [
            'mautic.transport.omnivery_factory' => [
                'class'        => \MauticPlugin\OmniveryMailerBundle\Mailer\Transport\MauticOmniveryTransportFactory::class,
                'arguments'    => [
                    'event_dispatcher',
                    'mautic.omnivery.http.client',
                    'monolog.logger.mautic',
                ],
                'tag'          => 'mailer.transport_factory',
            ],

            'mautic.transport.omnivery_api' => [
                'class'        => \MauticPlugin\OmniveryMailerBundle\Mailer\Transport\OmniveryApiTransport::class,
                'arguments'    => [
                    'event_dispatcher',
                    'mautic.omnivery.http.client',
                    'monolog.logger.mautic',
                    /*
                    'mautic.email.model.transport_callback',
                    'translator',
                    '%mautic.mailer_omnivery_max_batch_limit%',
                    '%mautic.mailer_omnivery_batch_recipient_count%',
                    '%mautic.mailer_omnivery_webhook_signing_key%',

                    'mautic.helper.core_parameters',
                     */
                ],
                'tag'          => 'mautic.mailer_transport',
            ],

            'mautic.omnivery.http.client' => [
                'class' => Symfony\Component\HttpClient\NativeHttpClient::class,
            ],
        ],
    ],

    'parameters' => [
        'mailer_omnivery_max_batch_limit'       => 20,
        'mailer_omnivery_batch_recipient_count' => 20,
        'mailer_omnivery_webhook_signing_key'   => '',
        'mailer_omnivery_host'                  => 'mg-api.omnivery.net',
    ],
];
