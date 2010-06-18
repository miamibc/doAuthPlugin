Thank you for registering!

Your login is: <?php echo $user->getUsername(); ?>

Your password is: <?php echo $password ?>

Use them to enter the site:

<?php echo url_for('@signin',array('absolute'=> true)) ?>