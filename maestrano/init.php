<?php

//-----------------------------------------------
// Define root folder and load base
//-----------------------------------------------
if (!defined('MAESTRANO_ROOT')) { define("MAESTRANO_ROOT", realpath(dirname(__FILE__))); }
if (!defined('ROOT_PATH')) { define('ROOT_PATH', realpath(dirname(__FILE__)) . '/../'); }
chdir(ROOT_PATH);

//-----------------------------------------------
// Load Maestrano library
//-----------------------------------------------
require_once ROOT_PATH . 'libraries/vendor/maestrano/maestrano-php/lib/Maestrano.php';
Maestrano::configure(ROOT_PATH . 'maestrano.json');

//-----------------------------------------------
// Require your app specific files here
//-----------------------------------------------

// Load Joomla
if (!defined('_JEXEC')){
  define( '_JEXEC', 1 );
  define( 'DS', DIRECTORY_SEPARATOR );
  define( 'JPATH_BASE', realpath(dirname(__FILE__).DS.'..').DS );
  require_once ( JPATH_BASE .'includes'.DS.'defines.php' );
  require_once ( JPATH_BASE .'includes'.DS.'framework.php' );
  
  // Mark afterLoad in the profiler.
  JDEBUG ? $_PROFILER->mark('afterLoad') : null;

  // Instantiate the application.
  $mainframe =& JFactory::getApplication('administrator');
  $mainframe->initialise();
}