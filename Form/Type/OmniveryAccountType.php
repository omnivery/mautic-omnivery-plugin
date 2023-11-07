<?php

namespace MauticPlugin\OmniveryMailerBundle\Form\Type;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class OmniveryAccountType extends AbstractType
{
    private $coreParametersHelper;

    public function __construct(CoreParametersHelper $coreParametersHelper)
    {
        $this->coreParametersHelper = $coreParametersHelper;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'host',
            TextType::class,
            [
                'label'      => 'mautic.omniverymailer.form.new.host',
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
                'data' => $options['data']['host'],
            ]
        );

        $apiKey = '';

        if (is_string($options['data']['api_key']) && strlen($options['data']['api_key']) > 4) {
            $apiKey = '***'.substr($options['data']['api_key'], -3, 4);
        }
        $builder->add(
            'api_key',
            TextType::class,
            [
                'label'      => 'mautic.omniverymailer.form.new.key',
                'label_attr' => [
                    'class' => 'control-label', ],
                'attr'       => [
                    'class'   => 'form-control',
                    // 'tooltip' => 'mautic.asset.config.form.max.size.tooltip',
                    ],
                'constraints' => [
                    /*new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),*/
                ],
                'data' => $apiKey,
            ]
        );

        $builder->add(
            'delete',
            CheckboxType::class,
            [
                'label'      => 'mautic.omniverymailer.form.new.delete',
                'label_attr' => [
                    'class' => 'control-label',
                ],
                'attr' => [
                    'class' => '',
                ],
                'data' => false,
            ]
        );

        $region = $options['data']['region'] ?? '';
        if (!strlen($region)) {
            $region = $this->coreParametersHelper->get('mailer_omnivery_region');
        }
        $builder->add(
            'region',
            TextType::class,
            [
                'label'      => 'mautic.omniverymailer.form.new.region',
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
                'data' => $region,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'omniveryconfig_account';
    }
}
