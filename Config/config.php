<?php

return [
    'name'        => 'OmniveryMailer',
    'description' => 'Integrate Mailer transport for Omnivery API (mautic 5)',
    'author'      => 'Matic Zagmajster',
    'version'     => '2.0.0',

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

            'mautic.omnivery.subscriber.callback' => [
                'class'     => \MauticPlugin\OmniveryMailerBundle\EventListener\CallbackSubscriber::class,
                'arguments' => [
                    'mautic.omnivery.model.transport_callback',
                    'mautic.helper.core_parameters',
                    'monolog.logger.mautic',
                ],
            ],
        ],

        'integrations' => [],

        'other' => [
            'mautic.omnivery.transport_factory' => [
                'class'        => \MauticPlugin\OmniveryMailerBundle\Mailer\Transport\MauticOmniveryTransportFactory::class,
                'arguments'    => [
                    'event_dispatcher',
                    'mautic.omnivery.http.client',
                    'monolog.logger.mautic',
                    'mautic.helper.core_parameters',
                ],
                'tag'          => 'mailer.transport_factory',
            ],

            'mautic.omnivery.http.client' => [
                'class' => Symfony\Component\HttpClient\NativeHttpClient::class,
            ],
        ],
    ],

    'parameters' => [],
];
