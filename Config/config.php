<?php
/*
 * @copyright   2020. All rights reserved
 * @author      Stanislav Denysenko<stascrack@gmail.com>
 *
 * @link        https://github.com/stars05
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

require_once MAUTIC_ROOT_DIR.'/plugins/MauticMailgunMailerBundle/.plugin-env.php';

return [
    'name'        => 'MailgunMailer',
    'description' => 'Integrate Swiftmailer transport for Mailgun API',
    'author'      => 'Stanislav Denysenko',
    'version'     => '1.0.0',

    'services' => [
        'forms' => [
            'mautic.form.type.mailgun.account' => [
                'class'     => \MauticPlugin\MauticMailgunMailerBundle\Form\Type\MailgunAccountType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],

            'mautic.form.type.mailgun.config' => [
                'class'     => \MauticPlugin\MauticMailgunMailerBundle\Form\Type\ConfigType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],

        'events' => [
            'mautic.mailgun.subscriber.config' => [
                'class'     => \MauticPlugin\MauticMailgunMailerBundle\EventListener\ConfigSubscriber::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],

        /*'integrations' => [
            'mautic.integration.mailgun' => [
                'class'     => 'MauticPlugin\MauticMailgunMailerBundle\Integration\MailgunMailerIntegration',
                'tags' => [
                    'mautic.config_integration',
                ],
                'arguments' => [
                    'mautic.helper.integration'
                ],
            ],
        ],*/

        'other' => [
            'mautic.transport.mailgun_api' => [
                'class'        => \MauticPlugin\MauticMailgunMailerBundle\Swiftmailer\Transport\MailgunApiTransport::class,
                'serviceAlias' => 'swiftmailer.mailer.transport.%s',
                'arguments'    => [
                    'mautic.email.model.transport_callback',
                    'mautic.mailgun.guzzle.client',
                    'translator',
                    '%mautic.mailer_mailgun_max_batch_limit%',
                    '%mautic.mailer_mailgun_batch_recipient_count%',
                    '%mautic.mailer_mailgun_webhook_signing_key%',
                    'monolog.logger.mautic',
                    'mautic.helper.core_parameters',
                ],
                'methodCalls' => [
                    'setApiKey' => ['%mautic.mailer_api_key%'],
                    'setDomain' => ['%mautic.mailer_host%'],
                    'setRegion' => ['%mautic.mailer_mailgun_region%'],
                ],
                'tag'          => 'mautic.email_transport',
                'tagArguments' => [
                    \Mautic\EmailBundle\Model\TransportType::TRANSPORT_ALIAS => 'mautic.email.config.mailer_transport.mailgun_api',
                    \Mautic\EmailBundle\Model\TransportType::FIELD_HOST      => true,
                    \Mautic\EmailBundle\Model\TransportType::FIELD_API_KEY   => true,
                ],
            ],
            'mautic.mailgun.guzzle.client' => [
                'class' => 'GuzzleHttp\Client',
            ],
        ],
    ],

    'parameters' => [
        'mailer_mailgun_max_batch_limit'       => \MauticPlugin\MauticMailgunMailerBundle\Env\MAX_BATCH_LIMIT,
        'mailer_mailgun_batch_recipient_count' => \MauticPlugin\MauticMailgunMailerBundle\Env\BATCH_RECIPIENT_COUNT,
        'mailer_mailgun_region'                => \MauticPlugin\MauticMailgunMailerBundle\Env\REGION,
        'mailer_mailgun_webhook_signing_key'   => \MauticPlugin\MauticMailgunMailerBundle\Env\WEBHOOK_SIGNING_KEY,
    ],
];
