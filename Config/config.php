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
    'description' => 'Integrate Swiftmailer transport for Mailgun API',
    'author'      => 'Matic Zagmajster',
    'version'     => '1.0.0',

    'services' => [
        'forms' => [
            'mautic.form.type.mailgun.account' => [
                'class'     => \MauticPlugin\OmniveryMailerBundle\Form\Type\MailgunAccountType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],

            'mautic.form.type.mailgun.config' => [
                'class'     => \MauticPlugin\OmniveryMailerBundle\Form\Type\ConfigType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],

        'events' => [
            'mautic.mailgun.subscriber.config' => [
                'class'     => \MauticPlugin\OmniveryMailerBundle\EventListener\ConfigSubscriber::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
        ],

        'integrations' => [],

        'other' => [
            'mautic.transport.mailgun_api' => [
                'class'        => \MauticPlugin\OmniveryMailerBundle\Swiftmailer\Transport\OmniveryApiTransport::class,
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
                    \Mautic\EmailBundle\Model\TransportType::FIELD_HOST      => false,
                    \Mautic\EmailBundle\Model\TransportType::FIELD_API_KEY   => true,
                ],
            ],
            'mautic.mailgun.guzzle.client' => [
                'class' => 'GuzzleHttp\Client',
            ],
        ],
    ],

    'parameters' => [
        'mailer_mailgun_max_batch_limit'       => \MauticPlugin\OmniveryMailerBundle\Env\MAX_BATCH_LIMIT,
        'mailer_mailgun_batch_recipient_count' => \MauticPlugin\OmniveryMailerBundle\Env\BATCH_RECIPIENT_COUNT,
        'mailer_mailgun_region'                => \MauticPlugin\OmniveryMailerBundle\Env\REGION,
        'mailer_mailgun_webhook_signing_key'   => \MauticPlugin\OmniveryMailerBundle\Env\WEBHOOK_SIGNING_KEY,
    ],
];
