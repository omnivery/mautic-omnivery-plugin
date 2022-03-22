<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$domainAccounts = [];
$fieldNames     = \array_keys($form->children);
foreach ($fieldNames as $name) {
    if (0 !== strpos($name, 'mailer_omnivery_account_')) {
        continue;
    }

    $domainAccounts[] = $name;
}

?>



<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.config.tab.omniveryconfig.global'); ?></h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_omnivery_batch_recipient_count']); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_omnivery_max_batch_limit']); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_omnivery_region']); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_omnivery_webhook_signing_key']); ?>
            </div>
        </div>
    </div>
    
</div>

<!-- begin: new domain -->
<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.config.tab.omniveryconfig.new_domain'); ?></h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_omnivery_new_host']); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_omnivery_new_api_key']); ?>
            </div>
        </div>
        
    </div>
</div> <!-- end: new domain -->

<!-- begin: Omnivery Accounts -->
<?php foreach ($domainAccounts as $accountKey) { ?>
    
    <?php $domain = $form->children[$accountKey]['host']->vars['value']; ?>
    <div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.omniverymailer.domain_config').$domain; ?></h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children[$accountKey]['delete']); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children[$accountKey]['region']); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children[$accountKey]['host']); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children[$accountKey]['api_key']); ?>
            </div>
        </div>

    </div>
</div> <!-- end: Omnivery Accounts  -->
<?php } ?>
