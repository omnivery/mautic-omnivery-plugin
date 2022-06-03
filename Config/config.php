<?php
/*
 * @copyright   2020. All rights reserved
 * @author      Stanislav Denysenko<stascrack@gmail.com>
 *
 * @link        https://github.com/stars05
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

require_once MAUTIC_ROOT_DIR.'/plugins/OmniveryMailerBundle/.plugin-env.php';

return [
    'name'        => 'OmniveryMailer',
    'description' => 'Integrate Swiftmailer transport for Omnivery API',
    'author'      => 'Matic Zagmajster',
    'version'     => '1.0.0',

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

            'mautic.omnivery.subscriber.email' => [
                'class'     => \MauticPlugin\OmniveryMailerBundle\EventListener\EmailSubscriber::class,
                'arguments' => [
                    'mautic.email.model.email',
                ],
            ],
        ],

        'integrations' => [],

        'other' => [
            'mautic.transport.omnivery_api' => [
                'class'        => \MauticPlugin\OmniveryMailerBundle\Swiftmailer\Transport\OmniveryApiTransport::class,
                'serviceAlias' => 'swiftmailer.mailer.transport.%s',
                'arguments'    => [
                    'mautic.email.model.transport_callback',
                    'mautic.omnivery.guzzle.client',
                    'translator',
                    '%mautic.mailer_omnivery_max_batch_limit%',
                    '%mautic.mailer_omnivery_batch_recipient_count%',
                    '%mautic.mailer_omnivery_webhook_signing_key%',
                    'monolog.logger.mautic',
                    'mautic.helper.core_parameters',
                ],
                'methodCalls' => [
                    'setApiKey' => ['%mautic.mailer_api_key%'],
                    'setDomain' => ['%mautic.mailer_host%'],
                ],
                'tag'          => 'mautic.email_transport',
                'tagArguments' => [
                    \Mautic\EmailBundle\Model\TransportType::TRANSPORT_ALIAS => 'mautic.email.config.mailer_transport.omnivery_api',
                    \Mautic\EmailBundle\Model\TransportType::FIELD_HOST      => true,
                    \Mautic\EmailBundle\Model\TransportType::FIELD_API_KEY   => true,
                ],
            ],
            'mautic.omnivery.guzzle.client' => [
                'class' => 'GuzzleHttp\Client',
            ],
        ],
    ],

    'parameters' => [
        'mailer_omnivery_max_batch_limit'       => \MauticPlugin\OmniveryMailerBundle\Env\MAX_BATCH_LIMIT, // *
        'mailer_omnivery_batch_recipient_count' => \MauticPlugin\OmniveryMailerBundle\Env\BATCH_RECIPIENT_COUNT,  // *
        'mailer_omnivery_webhook_signing_key'   => \MauticPlugin\OmniveryMailerBundle\Env\WEBHOOK_SIGNING_KEY,  // *
        'mailer_omnivery_host'                  => \MauticPlugin\OmniveryMailerBundle\Env\HOST,  // *
    ],
];
