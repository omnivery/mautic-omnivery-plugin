<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMailgunMailerBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\ConfigBundle\Event\ConfigEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticMailgunMailerBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    /**
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * Adds New Mailgun Account.
     *
     * If config for email domain already exists it will get overwritten.
     *
     * @param void
     */
    private function addNewMailgunAccount(&$currentConfig, &$config)
    {
        $emailDomain                 = $this->getEmailDomain($config['mailer_mailgun_new_host']);
        $currentConfig[$emailDomain] = [
                'host'    => $config['mailer_mailgun_new_host'],
                'api_key' => $config['mailer_mailgun_new_api_key'],
                'region'  => $config['mailer_mailgun_region'],  // Initialize with gloabl region setting.
        ];
        unset(
            $config['mailer_mailgun_new_host'],
            $config['mailer_mailgun_new_api_key']
        );
    }

    private function getEmailDomain($host)
    {
        $count = \substr_count($host, '.');
        if (1 == $count) {
            return $host;
        }

        $parts = explode('.', $host, 2);

        return $parts[1];
    }

    public function __construct(CoreParametersHelper $coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
            ConfigEvents::CONFIG_PRE_SAVE    => ['onConfigPreSave', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event)
    {
        $event->addForm([
            'bundle'     => 'MailgunMailerBundle',
            'formAlias'  => 'mailgunconfig',
            'formType'   => ConfigType::class,
            'formTheme'  => 'MauticMailgunMailerBundle:FormTheme\Config',
            'parameters' => $event->getParametersFromConfig('MauticMailgunMailerBundle'),
        ]);
    }

    public function onConfigPreSave(ConfigEvent $event)
    {
        $event->unsetIfEmpty([
            'mailer_mailgun_new_host',
            'mailer_mailgun_new_api_key',
        ]);

        $config = $event->getConfig('mailgunconfig');

        $currentConfig = $this->coreParametersHelper->get('mailer_mailgun_accounts', []);

        if (!empty($config['mailer_mailgun_new_host']) && !empty($config['mailer_mailgun_new_api_key'])) {
            $this->addNewMailgunAccount($currentConfig, $config);
        }

        // Fix the config structure of existing accounts.
        $keys = \array_keys($config);

        foreach ($keys as $k) {
            if (false === \strpos($k, 'mailer_mailgun_account_')) {
                continue;
            }

            $accountDetails                             = $config[$k];
            $domain                                     = $this->getEmailDomain($accountDetails['host']);
            if (0 === strpos($accountDetails['api_key'], '***')) {
                // Api key was not updated, make sure you save the correct string to config.
                $accountDetails['api_key'] = $currentConfig[$domain]['api_key'];
            }

            // Delete or save account details.
            if (isset($config[$k]['delete']) && true === $config[$k]['delete'] && isset($currentConfig[$domain])) {
                unset($currentConfig[$domain]);
                unset($config[$k]);
            } else {
                if (empty($accountDetails['delete'])) {
                    unset($accountDetails['delete']);
                }

                $currentConfig[$domain] = $accountDetails;
            }

            unset($config[$k]);
        }

        // Set Mailgun Accounts.
        $config['mailer_mailgun_accounts'] = $currentConfig;

        // Global signing key is not updated.
        if (isset($config['mailer_mailgun_webhook_signing_key']) && 0 === strpos($config['mailer_mailgun_webhook_signing_key'], '***')) {
            $config['mailer_mailgun_webhook_signing_key'] = $this->coreParametersHelper->get('mailer_mailgun_webhook_signing_key', '');
        }

        $event->setConfig($config, 'mailgunconfig');
    }
}
