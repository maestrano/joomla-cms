<?php
  /**
  * This controller processes a SAML response and deals with
  * user matching, creation and authentication
  * Upon successful authentication it redirects to the URL 
  * the user was trying to access.
  * Upon failure it redirects to the Maestrano access
  * unauthorized page
  *
  */

  require_once '../../init.php';

  // Maestrano libraries
  require_once 'maestrano/app/sso/MnoSsoUser.php';

  // Build SSO Response using SAMLResponse parameter value sent via POST request
  $resp = new Maestrano_Saml_Response($_POST['SAMLResponse']);
  if ($resp->isValid()) {
    // Get the user as well as the user group
    $user = new Maestrano_Sso_User($resp);
    $group = new Maestrano_Sso_Group($resp);
    
    // Get Maestrano User
    $sso_user = new MnoSsoUser($resp);

    // Find or create the User
    $sso_user->findOrCreate();
    
    // Once the user is created/identified, we store the maestrano session.
    // This session will be used for single logout
    $mnoSession = new Maestrano_Sso_Session($_SESSION, $user);
    $mnoSession->save();
    
    // Redirect the user to previous or home page
    if(isset($_SESSION['mno_previous_uri'])) {
      header('Location: ' . $_SESSION['mno_previous_uri']);
    } else {
      header('Location: /administrator');
    }
  } else {
    echo 'There was an error during the authentication process.<br/>';
    echo 'Please try again. If issue persists please contact support@maestrano.com';
  }
  
?>