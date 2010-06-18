<?php

class doAuthActions extends sfActions {
  public function executeSignin($request) {
    $user = $this->getUser();
    if ($user->isAuthenticated()) {
      return $this->redirect('@homepage');
    }

    $this->form = new SigninForm();
    
    $this->preSignin($request);

    if ($request->isMethod('post')) {
      $this->form->bind($request->getParameter('signin'));
      if ($this->form->isValid()) {
        $values = $this->form->getValues();
        $this->getUser()->signin($values['user'], array_key_exists('remember', $values) ? $values['remember'] : false);

        $this->postSignin($request);

        // always redirect to a URL set in app.yml
        // or to the referer
        // or to the homepage
        $signinUrl = sfConfig::get('app_doAuth_signin_url', $user->getReferer($request->getReferer()));

        return $this->redirect('' != $signinUrl ? $signinUrl : '@homepage');
      }
    }
    else {

      // if we have been forwarded, then the referer is the current URL
      // if not, this is the referer of the current request
      $user->setReferer($this->getContext()->getActionStack()->getSize() > 1 ? $request->getUri() : $request->getReferer());

      $module = sfConfig::get('sf_login_module');
      if ($this->getModuleName() != $module) {
        $this->getLogger()->warning('User is accessing signin action which is currently not configured in settings.yml. Please secure this action or update configuration');
      }
    }
  }


  public function executeSignout($request) {
    $this->getUser()->signOut();

    $signoutUrl = sfConfig::get('app_doAuth_signout_url', $request->getReferer());

    $this->redirect('' != $signoutUrl ? $signoutUrl : '@homepage');
  }

  public function executeRegister(sfWebRequest $request) {

    $this->form = new RegisterUserForm();

    $this->dispatcher->notify(new sfEvent($this, 'user.pre_register'));
    
    $this->preRegister($request);

    if ($request->isMethod('post')) {
      $this->form->bind($request->getParameter('user') );
      if ($this->form->isValid()) {
        $this->form->save();
        $this->user = $this->form->getObject();
        $this->user->setPassword($this->form->getValue('password'));
        $this->user->save();

        $this->dispatcher->notify(new sfEvent($this, 'user.registered',array('password'=> $this->form->getValue('password'))));
        
        $this->postRegister($request);

        // activate user
        if (!sfConfig::get('app_doAuth_activation',false)) {
          $this->user->setIsActive(1);
          $this->user->save();
          $this->firstSignin();
          $this->getUser()->setFlash('user_registered',1); // $this->getUser()->setFlash('notice',$this->getContext()->getI18N()->__('Congratulations! You are now registered.'));
        } else {
          $this->getUser()->setFlash('check_mail',1); // $this->getContext()->getI18N()->__('Please check your email to finish registration process')
        }

        // forward
        if ($params = sfConfig::get('app_doAuth_register_forward')) {
          list($module, $action) = $params;
          $this->forward($module, $action);
        }

        // or redirect
        $this->redirect(sfConfig::get('app_doAuth_register_redirect','@homepage'));
      }
    }
  }

  public function executeActivate(sfWebRequest $request) {

    $this->preActivate($request);

    $activation = Doctrine::getTable('UserActivationCode')
            ->createQuery('a')
            ->innerJoin('a.User u')
            ->where('a.code = ?', $request->getParameter('code'))
            ->fetchOne();

    $this->forward404Unless($activation,'Wrong activation code');

    $this->user = $activation->getUser();
    $this->user->setIsActive(1);
    $this->user->save();
    $activation->delete();

    $this->dispatcher->notify(new sfEvent($this, 'user.activated'));    
    $this->postActivate($request);

    $this->getUser()->getAttributeHolder()->removeNamespace('doUser');
    $this->firstSignin();

    $this->redirect(sfConfig::get('app_doAuth_register_redirect','@homepage'));
  }

  public function executeSecure($request) {
    $this->getResponse()->setStatusCode(403);
  }

  public function executeRequestPassword(sfWebRequest $request) {
    $this->form = new ResetPasswordForm();
    if ($request->isMethod('post')) {
      $this->form->bind($request->getParameter('reset_password'));
      if ($this->form->isValid()) {
        $user = Doctrine::getTable('User')->findOneByEmail($this->form->getValue('email') );
        doAuthMailer::sendPasswordRequest($this,$user);
        $this->getUser()->setFlash('request_sent',1); // $this->getUser()->setFlash('notice',$this->getContext()->getI18N()->__('You have requested a new password. Please, check your email and follow the instructions.'));
      }
    }
  }

  public function executeResetPassword(sfWebRequest $request) {
    $user = Doctrine::getTable('User')->find($request->getParameter('user'));
    if (!$user || $request->getParameter('code') != doAuthTools::passwordResetCode($user)) {
      return sfView::ERROR;
    }
    $password = doAuthTools::generatePassword();
    doAuthMailer::sendNewPassword($this,$user,$password);
    $user->setPassword($password);
    $user->save();
  }

  /**
   *
   * Automaticaly signs in current user after registration 
   */

  protected function firstSignin()
  {
    if (!sfConfig::get('app_doAuth_register_signin',false)) {
      $this->getUser()->signIn($this->user);
    }
  }

  // use this methods in your class to extend a functionality

  protected function preSignin(sfWebRequest $request) {}
  protected function postSignin(sfWebRequest $request) {}

  protected function preRegister(sfWebRequest $request) {}
  protected function postRegister(sfWebRequest $request) {}

  protected function preActivate(sfWebRequest $request) {}
  protected function postActivate(sfWebRequest $request) {}

}
