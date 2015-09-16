<?php
/**
 * @version    3.4.4
 * @package    Joomla.Plugin
 * @subpackage System.logout
 * @license    GNU/GPL
 */

defined('_JEXEC') or die;

// Do not run the authentication code below
// if we are in the maestrano sso actions (index/consume)
if (defined('MAESTRANO_ROOT')) return 1;

// Load the current application
$app = JFactory::getApplication();

// Add Star! framework to the Administrator pages
if ($app->getName() == 'administrator') {
  $doc = JFactory::getDocument();
  $doc->addScript('//cdn.maestrano.com/apps/mno_libs/mno-loader.js');
  $doc->addScriptDeclaration("
    window.mnoLoader.init('joomla','1');
  ");
}

// Load Maestrano
define( 'DS', DIRECTORY_SEPARATOR );
$root = realpath(dirname(__FILE__).DS.'..'.DS.'..'.DS.'..');
require_once $root . '/maestrano/init.php';

// Check Maestrano session and perform
// redirects based on context
if(Maestrano::sso()->isSsoEnabled()) {
  $mnoSession = new Maestrano_Sso_Session($_SESSION);

  if ($app->getName() == 'administrator') {
    // Destroy Session andr edirect to Maestrano logout page if action is logout
    $params = JUri::getInstance()->getQuery(true);
    if ($params && array_key_exists('task', $params) && $params['task'] == 'logout') {
      $session = JFactory::getSession();
      $session->destroy();
      session_unset();
      session_destroy();
    
      header("Location: " . Maestrano::sso()->getLogoutUrl());
      exit;
    }

    // Get User
    $user = JFactory::getUser();

    // Check user is logged in and mno session is still valid
    // (User redirected to SSO automatically if not logged in)
    if (!$user->id || !$mnoSession->isValid()) {
      header("Location: " . Maestrano::sso()->getInitPath());
      exit;
    }
  }
  
  if ($app->getName() == 'site') {
    // Get User
    $user = JFactory::getUser();
    
    // If user is logged in and is a maestrano user
    // then check session is still valid
    if ($user->id && $user->mno_uid) {
      if (!$mnoSession->isValid()) {
        header("Location: " . Maestrano::sso()->getInitPath());
        exit;
      }
    }
  }
}

?>