<?php use_helper('I18N') ?>

<h1><?php echo __('Sign in') ?></h1>

<?php if ($sf_user->hasFlash('check_mail') ): ?>
  <p><?php echo __('Please, check your email to finish process.'); ?></p>
<?php endif;?>

<form action="#" method="post">
  <table>
    <?php echo $form ?>
  </table>
  <input type="submit" value="<?php echo __('Sign in') ?>" />
</form>

<p><?php echo link_to( __('Register account'), '@register');?></p>
<p><?php echo link_to( __('Reset password'), '@request_password');?></p>