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
    if (0 !== strpos($name, 'mailer_mailgun_account_')) {
        continue;
    }

    $domainAccounts[] = $name;
}

?>



<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.config.tab.mailgunconfig.global'); ?></h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_mailgun_batch_recipient_count']); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_mailgun_max_batch_limit']); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_mailgun_region']); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_mailgun_webhook_signing_key']); ?>
            </div>
        </div>
    </div>
    
</div>

<!-- begin: new domain -->
<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.config.tab.mailgunconfig.new_domain'); ?></h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_mailgun_new_host']); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <?php echo $view['form']->row($form->children['mailer_mailgun_new_api_key']); ?>
            </div>
        </div>
        
    </div>
</div> <!-- end: new domain -->

<!-- begin: Mailgun Accounts -->
<?php foreach ($domainAccounts as $accountKey) { ?>
    
    <?php $domain = $form->children[$accountKey]['host']->vars['value']; ?>
    <div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.mailgunmailer.domain_config').$domain; ?></h3>
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
</div> <!-- end: Mailgun Accounts  -->
<?php } ?>
