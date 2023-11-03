<?php


namespace MauticPlugin\OmniveryMailerBundle\Form\Type;

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
            'mailer_omnivery_batch_recipient_count',
            IntegerType::class,
            [
                'label'      => 'mautic.omniverymailer.form.global.batch_recipient_count',
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
            'mailer_omnivery_max_batch_limit',
            IntegerType::class,
            [
                'label'      => 'mautic.omniverymailer.form.global.max_batch_limit',
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

        $webhookSigningKey = $options['data']['mailer_omnivery_webhook_signing_key'];
        if (strlen($webhookSigningKey) > 4) {
            $webhookSigningKey = '***'.substr($webhookSigningKey, -3, 4);
        } else {
            $webhookSigningKey = '***';
        }

        $builder->add(
            'mailer_omnivery_webhook_signing_key',
            TextType::class,
            [
                'label'      => 'mautic.omniverymailer.form.global.skey',
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
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'omniveryconfig';
    }
}
