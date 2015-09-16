<?php

/**
 * Configure App specific behavior for Maestrano SSO
 */
class MnoSsoUser extends Maestrano_Sso_User
{
  /**
   * Database connection
   * @var PDO
   */
  public $connection = null;

  /**
   * Extend constructor to inialize app specific objects
   *
   * @param OneLogin_Saml_Response $saml_response
   *   A SamlResponse object from Maestrano containing details
   *   about the user being authenticated
   */
  public function __construct($saml_response) {
    parent::__construct($saml_response);
    
    // Assign new attributes
    $this->connection = JFactory::getDBO();
  }
  
  /**
  * Find or Create a user based on the SAML response parameter and Add the user to current session
  */
  public function findOrCreate() {
    // Find user by uid. Is it exists, it has already signed in using SSO
    $local_id = $this->getLocalIdByUid();
    $new_user = ($local_id == null);
    // Find user by email
    if($local_id == null) { $local_id = $this->getLocalIdByEmail(); }

    if ($local_id) {
      // User found, load it
      $this->local_id = $local_id;
      $this->syncLocalDetails($new_user);
    } else {
      // New user, create it
      $this->local_id = $this->createLocalUser();
      $this->setLocalUid();
    }

    // Add user to current session
    $this->setInSession();
  }
  
  /**
   * Sign the user in the application. 
   * Parent method deals with putting the mno_uid, 
   * mno_session and mno_session_recheck in session.
   *
   * @return boolean whether the user was successfully set in session or not
   */
  protected function setInSession() {
    $db = $this->connection;

    if ($this->local_id) {
      $user = JUser::getInstance($this->local_id);

      // Register the needed session variables
      $session = JFactory::getSession();
      $session->set('user', $user);

      // Check to see if the session already exists.
      $app = JFactory::getApplication();
      $app->checkSession();

      // Update the user related fields for the Joomla sessions table.
      $db->setQuery(
        'UPDATE '.$db->quoteName('#__session') .
        ' SET '.$db->quoteName('guest').' = '.$db->quote($user->get('guest')).',' .
        ' '.$db->quoteName('username').' = '.$db->quote($user->get('username')).',' .
        ' '.$db->quoteName('userid').' = '.(int) $user->get('id') .
        ' WHERE '.$db->quoteName('session_id').' = '.$db->quote($session->getId())
      );
      $db->query();
      
      // Check wether we should redirect to 'site' or 'administrator'
      $redirect = '/';
      if ($user->authorise('core.login.admin')) {
        $redirect = '/administrator';
      }
      // $maestrano = MaestranoService::getInstance();
      // $maestrano->setAfterSsoSignInPath($redirect);

      // Hit the user last visit field
      $user->setLastVisit();
      
      return true;
    } else {
      return false;
    }
  }
  
  /**
   * Used by createLocalUserOrDenyAccess to create a local user 
   * based on the sso user.
   * If the method returns null then access is denied
   *
   * @return the ID of the user created, null otherwise
   */
  protected function createLocalUser() {    
    // Set a temporary admin user
    $admin = JUser::getInstance();
    $admin->username = 'maestrano';
    
    $config = JFactory::getConfig();
    $config->set('root_user','maestrano');
    $session = JFactory::getSession();
    $session->set('user', $admin);
    
    // Create the user
    $user = $this->buildLocalUser();
    $user->save();
    
    // Remove the root user
    $config->set('root_user',null);
    $session->set('user', null);
    
    return $user->id;
  }
  
  /**
   * Build a local user for creation
   *
   * @return a hash of user attributes
   */
  protected function buildLocalUser() {
    $fullname = ($this->getFirstName() . ' ' . $this->getLastName());
    $password = $this->generatePassword();
    
    $user = JUser::getInstance();
    $attr = Array(
      'name'      => $fullname,
      'email'     => $this->getEmail(),
      'username'  => $this->uid,
      'password'  => $password,
      'password2' => $password,
      'groups'    => $this->getRoleToAssign()
    );
    $user->bind($attr);
    
    return $user;
  }
  
  /**
   * Return the role to give to the user based on context
   * If the user is the owner of the app or at least Admin
   * for each organization, then it is given the role of 'Admin'.
   * Return 'User' role otherwise
   *
   * @return the ID of the user created, null otherwise
   */
  public function getRoleToAssign() {
    $default_user_role = Array();
    $default_admin_role = Array(8);

    switch($this->getGroupRole()) {
      case 'Member':
        return $default_user_role;
      case 'Power User':
        return $default_user_role;
      case 'Admin':
        return $default_admin_role;
      case 'Super Admin':
        return $default_admin_role;
      default:
        return $default_user_role;
    }
  }
  
  /**
   * Get the ID of a local user via Maestrano UID lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByUid() {
    $db = $this->connection;
    
    $query = $db->getQuery(true);
    $query->select($db->quoteName('id'));
    $query->from($db->quoteName('#__users'));
    $query->where($db->quoteName('mno_uid') . ' = '. $db->quote($this->uid));
    $db->setQuery($query);
    $result = $db->loadResult();
    
    if ($result) {
      return intval($result);
    }
    
    return null;
  }
  
  /**
   * Get the ID of a local user via email lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByEmail() {
    $db = $this->connection;
    
    $query = $db->getQuery(true);
    $query->select($db->quoteName('id'));
    $query->from($db->quoteName('#__users'));
    $query->where($db->quoteName('email') . ' = '. $db->quote($this->getEmail()));
    $db->setQuery($query);
    $result = $db->loadResult();
    
    if ($result) {
      return intval($result);
    }
    
    return null;
  }
  
  /**
   * Set all 'soft' details on the user (like name, surname, email)
   * Implementing this method is optional.
   *
   * @return boolean whether the user was synced or not
   */
   protected function syncLocalDetails() {
     $db = $this->connection;
     
     if($this->local_id) {
       $fields = Array(
         $db->quoteName('name') . ' = '. $db->quote($this->getFirstName() . ' ' . $this->getLastName()),
         $db->quoteName('username') . ' = '. $db->quote($this->uid),
       );

       $query = $db->getQuery(true);
       $query->update($db->quoteName('#__users'));
       $query->set($fields);
       $query->where($db->quoteName('id') . ' = '. $db->quote($this->local_id));
       $db->setQuery($query);
       $upd = $db->query();
       
       return $upd;
     }
     
     return false;
   }
  
  /**
   * Set the Maestrano UID on a local user via id lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function setLocalUid() {
    $db = $this->connection;
    
    if($this->local_id) {
      $fields = Array(
        $db->quoteName('mno_uid') . ' = '. $db->quote($this->uid),
      );

      $query = $db->getQuery(true);
      $query->update($db->quoteName('#__users'));
      $query->set($fields);
      $query->where($db->quoteName('id') . ' = '. $db->quote($this->local_id));
      $db->setQuery($query);
      $upd = $db->query();
      
      return $upd;
    }
    
    return false;
  }

  /**
  * Generate a random password.
  * Convenient to set dummy passwords on users
  *
  * @return string a random password
  */
  protected function generatePassword() {
    $length = 20;
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
  }
}