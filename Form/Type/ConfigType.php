<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMailgunMailerBundle\Form\Type;

use Mautic\CoreBundle\Form\DataTransformer\ArrayStringTransformer;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticMailgunMailerBundle\Form\Type\MailgunAccountType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ConfigType extends AbstractType
{
    private $coreParametersHelper;

    public function __construct($coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'mailer_mailgun_batch_recipient_count',
            IntegerType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.global.batch_recipient_count',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),
                ],
            ]
        );

        $builder->add(
            'mailer_mailgun_max_batch_limit',
            IntegerType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.global.max_batch_limit',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),
                ],
            ]
        );

        $builder->add(
            'mailer_mailgun_region',
            TextType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.global.region',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),
                ],
            ]
        );

        $webhookSigningKey = $options['data']['mailer_mailgun_webhook_signing_key'];
        if (strlen($webhookSigningKey) > 4) {
            $webhookSigningKey = '***'.substr($webhookSigningKey, -3, 4);
        } else {
            $webhookSigningKey = '***';
        }

        $builder->add(
            'mailer_mailgun_webhook_signing_key',
            TextType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.global.skey',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),
                ],
                'data' => $webhookSigningKey,
            ]
        );

        // Add new account

        $builder->add(
            'mailer_mailgun_new_host',
            TextType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.new.host',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    /*new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),*/
                ],
            ]
        );

        $builder->add(
            'mailer_mailgun_new_api_key',
            TextType::class,
            [
                'label'      => 'mautic.mailgunmailer.form.new.key',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    /*new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),*/
                ],
            ]
        );

        $accounts = $this->coreParametersHelper->get('mailer_mailgun_accounts', []);
        $i        = 0;
        foreach ($accounts as $domain => $details) {
            // Host
            $builder->add(
                sprintf('mailer_mailgun_account_%d', $i),
                MailgunAccountType::class,
                [
                    'label'      => 'mautic.mailgunmailer.form.account',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'   => 'form-control',
                        // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                        ],
                    'constraints' => [
                        /*new NotBlank([
                            'message' => 'mautic.core.value.required',
                        ]),*/
                    ],
                    'data' => $details,
                ]
            );

            ++$i;
        }
        /*echo '<pre>';
        var_dump($accounts);
        echo '</pre>';*/
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'mailgunconfig';
    }
}
