<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

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
                <?php echo $view['form']->row($form->children['mailer_omnivery_webhook_signing_key']); ?>
            </div>
        </div>
    </div>
    
</div>

