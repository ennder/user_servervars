<?php

/**
 * ownCloud - user_servervars
 *
 * @author Jean-Jacques Puig
 * @copyright 2014 Jean-Jacques Puig // ESPCI ParisTech
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/*
  abstract OC_User_Backend:
    * keeping inherited:
        getSupportedActions()

    * overriding:
        getHome($uid)

  implements OC_User_Interface:
    * keeping inherited:
        implementsActions($actions);

    * overriding:
        deleteUser($uid);
        getUsers($search = '', $limit = NULL, $offset = NULL);
        userExists($uid);
        getDisplayName($uid);
        getDisplayNames($search = '', $limit = NULL, $offset = NULL);
        hasUserListings();

  hooks:
    * OC_User > post_login
    * OC_User > logout

  Logging consts: DEBUG, INFO, WARN, ERROR, FATAL
*/


\OCP\App::checkAppEnabled(basename(dirname(__FILE__)));


class OC_USER_SERVERVARS extends OC_User_Backend {


  /**
  * Overriding inherited defaults from OC_User_Backend
  */
  protected $possibleActions = array(
    OC_USER_BACKEND_CHECK_PASSWORD  => 'checkPassword',
    OC_USER_BACKEND_GET_HOME        => 'getHome',
    OC_USER_BACKEND_GET_DISPLAYNAME => 'getDisplayName',
    OC_USER_BACKEND_PROVIDE_AVATAR  => 'canChangeAvatar',
    OC_USER_BACKEND_COUNT_USERS     => 'countUsers',
  );

  /**
  * It is unclear (inheritance ?) why core backend objects have per instance
  * $possibleActions rather than class (static) members. Keeping the same
  * design for the following class members:
  */
                              # *Evaluate* (these are php expressions) to:

  protected $ssoUrl;          # - SSO URL to redirect to on unauthenticated user
                              #   (string)

  protected $sloUrl;          # - SLO URL to redirect to on user logout
                              #   (string)


  protected $autocreate;      # - whether to autocreate user
                              #   (boolean)

  protected $updateUserData;  # - whether to update user's data on login
                              #   (boolean)

  protected $userDeletion;    # - allow OC management UI to delete user data
                              #   (boolean)

  protected $userChangeAvatar;# - allow user to change his avatar file in OC
                              #   (boolean)


  protected $baseDirectory;   # - users base directory
                              #   (string)


  protected $uid;             # - user id (uid)
                              #   (string)

  protected $displayname;     # - user display name (cn)
                              #   (string)

  protected $email;           # - user email
                              #   (string)

  protected $avatar;          # - user avatar (base64 encoded png or jpg)
                              #   (string)


  /**
  * Construct with a config file defining default values in a $config array;
  * default config is otherwise in config/parameters.php
  *
  * @param string $config_file A config_file defining the $config
  * array
  *
  * @return Instance of current class
  */
  function __construct($config_file = NULL) {
    $this->init($config_file);
  }


  /**
  * Wrap log in a more 'syslog' way
  *
  * @param int $level Log level
  * @param string $msg Log message
  */
  protected function log($level, $msg) {
    static $moduleName = NULL;

    if ($moduleName === NULL)
      $moduleName = basename(dirname(__FILE__));

    \OCP\Util::writeLog($moduleName, $msg, $level);
  }

  /**
  * Convert from parameter notation to caml notation
  *
  * @param string A parameter name
  *
  * @return A caml name
  */
  function param2caml($string) {
    static $alphabet = 'abcdefghijklmnopqrstuvwxyz';
    static $search = NULL;
    static $replace = NULL;
 
    if ($search === NULL) {
      $search
        = array_map(
                    function($v) { return '_' . $v; },
                    preg_split('//', $alphabet, -1, PREG_SPLIT_NO_EMPTY)
        );
    }

    if ($replace === NULL) {
      $replace
        = preg_split('//', strtoupper($alphabet), -1, PREG_SPLIT_NO_EMPTY);
    }

    return str_ireplace($search, $replace, $string);
  }

  /**
  * Initialize members with a config file defining default values in a $config
  * array; default config is otherwise in config/parameters.php
  * Then registers hooks
  *
  * @param string $config_file A config_file defining the $config array
  */
  function init($config_file = NULL) {
    $path = dirname(__FILE__);

    if ($config_file === NULL) {
      $config_file = $path . '/config/parameters.php';
    }

    require $config_file;

    if (!is_array($config)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('init(%s) failed: $config array is undefined',
                          $config_file));

      return FALSE;
    }

    $module = basename($path);
    foreach ($config as $name => $default_value) {
      $attribute = $this->param2caml($name);
      $this->$attribute = OCP\Config::getAppValue($module,
                                                  $name,
                                                  $default_value);
    }

    $hooks = array(
                    'post_login'  => 'postLogin',
                    'logout'      => 'logout',
                    );

    foreach($hooks as $hook => $callback) {
      # Members are not static (cf. above); using static functions would be
      # misleading and cumbersome.
      # In private/hook.php, hook actually is a Callable: using here instance
      # instead of classname.
      # If needed, this class may evolve to a singleton in the future.
      OCP\Util::connectHook('OC_User', $hook, $this, $callback);
    }
  }

  /**
  * Evaluate user attribute with specified mapping
  *
  * @param string $mapping The mapping being evaluated
  * @param string $uid The user id, if available
  *
  * @return mixed or FALSE if $mapping yields an *empty* value
  */
  protected function evaluateMapping($mapping, $uid = NULL) {
    static $cache = array();

    if (!array_key_exists($mapping, $cache))
      $cache[$mapping] = array();

    if (!array_key_exists($uid, $cache[$mapping])) {
      # anonymous function required to address limitations for access to PHP
      # superglobals such as $_SERVER...
      $f = create_function('$uid', sprintf('return %s;', $this->$mapping));

      try {
        $value = $f($uid);
      }
      catch(Exception $e) {
        $this->log(\OCP\Util::ERROR,
                    sprintf('evaluateMapping(%s, %s) failed: %s',
                            $mapping, $uid, $e));

        $value = FALSE;
      }

      if (empty($value))
        $cache[$mapping][$uid] = FALSE;
      else
        $cache[$mapping][$uid] = $value;
    }

    return $cache[$mapping][$uid];
  }

  /**
  * Wrap common SQL queries
  *
  * @param string $query An SQL query
  * @param string $params Query parameters
  * @param int $limit SQL limit
  * @param int $offset SQL offset
  *
  * @return A result
  */
  protected function dbExec($query, $params = NULL, $limit = 0, $offset = 0) {
    static $shortModuleName = NULL;

    if ($shortModuleName === NULL)
      $shortModuleName
        = preg_replace('/^[^_]*_/', '', basename(dirname(__FILE__)));

    $query = preg_replace('/\*APP\*/', $shortModuleName, $query);

    $this->log(\OCP\Util::DEBUG,
                __FUNCTION__
                  . '(' . $query . ', ' . print_r($params, TRUE) . ', '
                        . $limit . ', ' . $offset . ')');

    $query = \OCP\DB::prepare($query);

    return $query->execute($params);
  }

  /**
  * Add a user to App database
  *
  * @param string $uid The user id
  *
  * @return $uid or FALSE
  */
  protected function userAdd($uid) {
    $query = 'INSERT'
              . ' INTO'
                . ' `*PREFIX**APP*_users`'
                . ' (`uid`)'
              . ' VALUES'
                . ' (?)';
    $result = $this->dbExec($query, array($uid), 1);
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('userAdd(%s) failed: %s',
                          $uid, \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }

    $this->log(\OCP\Util::INFO,
                sprintf('userAdd(%s) succeeded',
                        $uid));

    return $uid;
  }

  /**
  * Update user's display name
  *
  * APP specific note:
  *   We volontarily do not check for actual user existence in base
  *
  * @param string $uid The user id
  *
  * @return new display name or FALSE
  */
  protected function updateDisplayName($uid) {
    $displayname = $this->evaluateMapping('displayname', $uid);
    if ($displayname === FALSE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('updateDisplayName(%s): no SSO display name found',
                          $uid));

      return FALSE;
    }

    $query = 'UPDATE'
                . ' `*PREFIX**APP*_users`'
              . ' SET'
                . ' `displayname` = ?'
              . ' WHERE'
                . ' `uid` = ?';
    $result = $this->dbExec($query, array($displayname, $uid), 1);
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('updateDisplayName(%s) failed: %s',
                          $uid, \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }

    $this->log(\OCP\Util::DEBUG,
                sprintf('updateDisplayName(%s): saved user display name \'%s\'',
                        $uid, $displayname));

    return $displayname;
  }

  /**
  * Update user's email setting
  *
  * APP specific note:
  *   We volontarily do not check for actual user existence in base
  *
  * @param string $uid The user id
  *
  * @return new email or FALSE
  */
  protected function updateEmail($uid) {
    // Jebat 8.1 compatibility 2016-12-28
    $config = \OC::$server->getConfig();

    $email = $this->evaluateMapping('email', $uid);
    if ($email === FALSE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('updateEmail(%s): no SSO email value found',
                          $uid));

      return FALSE;
    }

    // Jebat 8.1 compatibility 2016-12-28
    if ($email !== $config->getUserValue($uid, 'settings', 'email')) {
    //if ($email !== OC_Preferences::getValue($uid, 'settings', 'email')) {
      $this->log(\OCP\Util::DEBUG,
                  sprintf('updateEmail(%s): setting email \'%s\' for user',
                          $uid, $email));

      // Jebat 8.1 compatibility 2016-12-28
      $config->setUserValue($uid, 'settings', 'email', $email);
      //OC_Preferences::setValue($uid, 'settings', 'email', $email);
    }

    return $email;
  }

  /**
  * Update user's avatar file
  *
  * APP specific note:
  *   We volontarily do not check for actual user existence in base
  *
  * @param string $uid The user id
  *
  * @return boolean
  */
  protected function updateAvatar($uid) {
    $avatar = $this->evaluateMapping('avatar', $uid);
    if ($avatar === FALSE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('updateAvatar(%s): no SSO avatar value found',
                          $uid));

      return FALSE;
    }

    if (!preg_match('|^data:image/([^;]+);base64,([a-zA-Z0-9+/]+=?)|', $avatar, $match)) {
      $extension = FALSE;
    }
    else {
      $data = base64_decode($match[2]);
      if ($data === FALSE) {
        $extension = FALSE;
      }
      else {
        $extension = $match[1];
      }
    }

    switch ($extension) {
      case 'jpeg':
        $extension = 'jpg';
      case 'jpg':
      case 'png':
        break;

      default:
        $this->log(\OCP\Util::WARN,
                    sprintf('updateAvatar(%s): avatar data does not look valid',
                            $uid));

        return FALSE;
    }

    $filename = $this->getHome($uid);
    if ($filename === FALSE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('updateAvatar(%s): no homedir to save avatar to !',
                          $uid));

      return FALSE;
    }

    $filename .= '/avatar.' . $extension;

    if (file_put_contents($filename, $data) === FALSE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('updateAvatar(%s): error while writing to \'%s\'',
                          $uid, $filename));

      return FALSE;
    }

    $this->log(\OCP\Util::DEBUG,
                sprintf('updateAvatar(%s): saved avatar in \'%s\'',
                        $uid, $filename));

    return TRUE;
  }

  /**********************************
  * IMPLEMENTATION OF UserInterface *
  ***********************************/

  /**
  * INHERITED:
  *
  *   public function implementsActions($actions);
  * 
  * Check if backend implements actions. Returns the supported actions as int
  * to be compared with OC_USER_BACKEND_CREATE_USER etc.
  *
  * @param int $actions Bitwise-or'ed actions
  *
  * @return boolean
  *
  */

  /**
  * Delete a user
  *
  * APP specific note:
  *   be aware that this (only) deletes user from *APP* table and OC user data
  *   ! User will be automatically re-provisioned if he connects with SSO and
  *   autocreation is enabled
  *
  * @param string $uid The user to delete
  *
  * @return boolean
  */
  function deleteUser($uid) {
    $allow = $this->evaluateMapping('userDeletion', $uid);
    if ($allow !== TRUE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('deleteUser(%s) failed:'
                            . ' userDeletion not allowed by config',
                          $uid));

      return FALSE;
    }

    $query = 'DELETE'
              . ' FROM'
                . ' `*PREFIX**APP*_users`'
              . ' WHERE'
                . ' `uid` = ?';
    $result = $this->dbExec($query, array($uid), 1);
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('deleteUser(%s) failed: %s',
                          $uid, \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }
    if($result === '0') {
      $this->log(\OCP\Util::WARN,
                  sprintf('deleteUser(%s) failed: no such user',
                          $uid));

      # Return TRUE because this result is OK: the caller expects to know if
      # user no longer exists in *APP* table, and this is most certainly so.
      return TRUE;
    }

    $this->log(\OCP\Util::INFO,
                sprintf('deleteUser(%s) succeeded',
                        $uid));

    return TRUE;
  }

  /**
  * Get a list of all users
  *
  * APP specific note:
  *    returns users which have historicaly succeeded a login (for which there
  *    is an entry in *APP* table.
  *
  * @param string $search A search string
  *
  * @return An array of all known uids
  */
  function getUsers($search = '', $limit = 10, $offset = 0) {
    $query = 'SELECT'
                . ' `uid`'
              . ' FROM'
                . ' `*PREFIX**APP*_users`';
    if (strlen($search)) {
      $search = '%' . $search . '%';

      $query .= ' WHERE'
                  . ' `uid` LIKE ?'
                . ' OR'
                  . ' `displayname` LIKE ?';

      $params = array($search, $search);
    }
    else {
      $params = array();
    }
    $result = $this->dbExec($query, $params, $limit, $offset);
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('getUsers(%s) failed: %s',
                          $uid, \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }

    $user = array();
    $records = $result->fetchAll();
    foreach ($records as $key => $record)
      $user[$key] = $record['uid'];

    return $user;
  }

  /**
  * Check if a user exists
  *
  * @param string $uid The username
  *
  * @return boolean
  */
  function userExists($uid) {
    $query = 'SELECT'
                . ' 1'
              . ' FROM'
                . ' `*PREFIX**APP*_users`'
              . ' WHERE'
                . ' `uid` = ?';
    $result = $this->dbExec($query, array($uid));
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('userExists(%s) failed: %s',
                          $uid, \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }

    if (intval($result->fetchOne()) === 1) {
      return TRUE;
    }

    return FALSE;
  }

  /**
  * Get a user's display name
  *
  * @param string $uid The user ID
  *
  * @return string
  */
  function getDisplayName($uid) {
    $query = 'SELECT'
                . ' `displayname`'
              . ' FROM'
                . ' `*PREFIX**APP*_users`'
              . ' WHERE'
                . ' `uid` = ?';
    $result = $this->dbExec($query, array($uid));
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('getDisplayName(%s) failed: %s',
                          $uid, \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }

    $displayname = $result->fetchOne();
    if ($displayname === FALSE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('getDisplayName(%s): no user entry in database',
                          $uid));
    }

    if (strlen($displayname)) {
      return $displayname;
    }

    $this->log(\OCP\Util::WARN,
                sprintf('getDisplayName(%s): not found; providing uid',
                        $uid));

    return $uid;
  }

  /**
  * Get a list of all display names
  *
  * @param string $search A query string
  * @param int $limit SQL limit
  * @param int $offset SQL offset
  *
  * @return Array of displaynames (values) and the corresponding uids (keys)
  */
  function getDisplayNames($search = '', $limit = NULL, $offset = NULL) {
    $query = 'SELECT'
                . ' `uid`,'
                . ' `displayname`'
              . ' FROM'
                . ' `*PREFIX**APP*_users`';
    if (strlen($search)) {
      $search = '%' . $search . '%';

      $query .= ' WHERE'
                  . ' `uid` LIKE ?'
                . ' OR'
                  . ' `displayname` LIKE ?';

      $params = array($search, $search);
    }
    else {
      $params = array();
    }
    $result = $this->dbExec($query, $params, $limit, $offset);
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('getDisplayNames(%s) failed: %s',
                          $uid, \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }

    $user = array();
    $records = $result->fetchAll();
    foreach ($records as $record)
      $user[$record['uid']] = $record['displayname'];

    return $user;
  }

  /**
  * Check if user listing is available
  *
  * @return boolean
  */
  function hasUserListings() {
    return TRUE;
  }

  /****************************
  * IMPLEMENTATION OF Actions *
  *****************************/

  /**
  * INHERITED:
  *
  *   public function getSupportedActions();
  * 
  * Get all supported actions. Returns the supported actions as int to be
  * compared with OC_USER_BACKEND_CREATE_USER etc.
  *
  * @param int $actions Bitwise-or'ed actions
  *
  * @return int Bitwise-or'ed actions
  *
  */

  /**
  * Check if the password is correct without logging in the user
  *
  * APP specific note:
  *    if $uid is current connected user (and autocreate is enabled), will
  *    create user !
  *
  * @param string $uid Username
  * @param string $password Password
  *
  * @return string (user id or FALSE)
  */
  function checkPassword($uid, $password) {
    $password = '********';

    $sso_uid = $this->evaluateMapping('uid', $uid);
    if ($sso_uid === FALSE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('checkPassword(%s, %s) failed:'
                            . ' no SSO data available',
                          $uid, $password));

      return FALSE;
    }

    if ($sso_uid !== $uid) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('checkPassword(%s, %s) failed:'
                          . ' SSO uid %s mismatch with OC uid',
                          $uid, $password, $sso_uid));

      return FALSE;
    }

    $this->log(\OCP\Util::DEBUG,
                sprintf('checkPassword(%s, %s): auth success',
                        $uid, $password));

    if (!($this->userExists($uid))) {
      $autocreate = $this->evaluateMapping('autocreate', $uid);
      if ($autocreate !== TRUE) {
        $this->log(\OCP\Util::WARN,
                    sprintf('checkPassword(%s, %s) failed to add user:'
                              . ' autocreate not allowed by config',
                            $uid, $password));

        return FALSE;
      }

      return $this->userAdd($uid);
    }

    return $uid;
  }

  /**
  * Get user's home directory
  *
  * APP specific note:
  *   We volontarily do not check for actual user existence in base
  *
  * @param string $uid Username
  *
  * @return string|FALSE
  */
  function getHome($uid) {
    $base = $this->evaluateMapping('baseDirectory', $uid);
    if ($base === FALSE) {
      $base = \OCP\Config::getSystemValue('datadirectory',
                                          \OC::$SERVERROOT . '/data');
      $this->log(\OCP\Util::DEBUG,
                  sprintf('getHome(%s): '
                            . ' no base directory found; using %s',
                          $uid, $base));
    }

    return $base . '/' . $uid;
  }

  /**
  * Check whether user is allowed to change his avatar
  *
  * APP specific note:
  *   We volontarily do not check for actual user existence in base
  *
  * @param string $uid Username
  *
  * @return boolean
  */
  function canChangeAvatar($uid) {
    $allow = $this->evaluateMapping('userChangeAvatar', $uid);
    if ($allow !== TRUE) {
      $this->log(\OCP\Util::WARN,
                  sprintf('canChangeAvatar(%s) failed:'
                            . ' userChangeAvatar not allowed by config',
                          $uid));

      return FALSE;
    }

    return TRUE;
  }

  /**
  * Count (known) users in backend
  *
  * @return int|boolean
  */
  function countUsers() {
    $query = 'SELECT'
                . ' COUNT(*)'
              . ' FROM'
                . ' `*PREFIX**APP*_users`';
    $result = $this->dbExec($query);
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('countUsers failed: %s',
                          \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }

    return $result->fetchOne();
  }

  /********
  * HOOKS *
  *********/

  /**
  * If required (just created) or permitted (update_user_data), updates user's
  * data: displayname (in APP table), email (in OC preferences table), avatar
  * (in file system)
  *
  * APP specific note:
  *   We volontarily do not check for actual user existence in base
  *
  * @param string $parameters Post login parameters (including 'uid')
  *
  * @return boolean
  */
  function postLogin($parameters) {
    $parameters['password'] = '********';

    $uid = $parameters['uid'];

    $this->log(\OCP\Util::INFO,
                sprintf('postLogin(%s): logging in user %s',
                        print_r($parameters, TRUE), $uid));

    $just_created = FALSE;
    $updateUserData = $this->evaluateMapping('updateUserData', $uid);
    if (!$updateUserData) {
      $query = 'SELECT'
                  . ' 1'
                . ' FROM'
                  . ' `*PREFIX**APP*_users`'
                . ' WHERE'
                  . ' `uid` = ?'
                . ' AND'
                  . ' `lastlogin` IS NULL';
      $result = $this->dbExec($query, array($uid));
      if (\OCP\DB::isError($result)) {
        $this->log(\OCP\Util::ERROR,
                    sprintf('postLogin(%s) failed: %s',
                            print_r($parameters, TRUE),
                            \OCP\DB::getErrorMessage($result)));

        return FALSE;
      }

      if ($result->fetchOne() === '1') {
        $just_created = TRUE;
      }
    }

    $query = 'UPDATE'
                . ' `*PREFIX**APP*_users`'
              . ' SET'
                . ' `lastlogin` = NOW()'
              . ' WHERE'
                . ' `uid` = ?';
    $result = $this->dbExec($query, array($uid), 1);
    if (\OCP\DB::isError($result)) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('postLogin(%s) failed: %s',
                          print_r($parameters, TRUE),
                          \OCP\DB::getErrorMessage($result)));

      return FALSE;
    }

    if ($just_created || $updateUserData) {
      $this->updateDisplayName($uid);
      $this->updateEmail($uid);
      $this->updateAvatar($uid);
    }

    return TRUE;
  }

  /**
  * Destroy OC user session and redirect to Single Log Out URL
  *
  * @param string $parameters Logout parameters (couldn't find 'uid' ?!)
  *
  * @return TRUE or exit with HTTP REDIRECT
  */
  function logout($parameters) {
    if (isset($parameters['uid'])) {
      $uid = $parameters['uid'];
    }
    else {
      $uid = NULL;
    }

    $this->log(\OCP\Util::INFO,
                sprintf('logout(%s): logging out user %s',
                        print_r($parameters, TRUE), $uid));

    OC_User::unsetMagicInCookie();
    session_unset();
    session_destroy();

    $sloUrl = $this->evaluateMapping('sloUrl', $uid);
    if ($sloUrl !== FALSE) {
        OCP\Response::redirect($sloUrl);

        exit();
    }

    return TRUE;
  }

  /************
  * AUTOLOGIN *
  *************/

  /**
  * Attempt to login with SSO user
  *
  * @return boolean or REDIRECT to SSO URL or REDIRECT to GET URL
  */
  function login() {
    $sso_uid = $this->evaluateMapping('uid');
    if ($sso_uid === FALSE) {
      // 2016/12/28 Jebat Owncloud > 8
      $sso_url = $this->evaluateMapping('ssoUrl');
      //$sso_url = $this->evaluateMapping('ssoURL');
      if ($sso_url === FALSE) {
        $this->log(\OCP\Util::ERROR,
                    sprintf('login() failed: user is not authenticated'
                              . ' and no SSO URL to redirect to'));

        return FALSE;
      }
      else {
        OCP\Response::redirect($sso_url);

        exit();
      }
    }

    if (!OCP\User::isLoggedIn() && !OC_User::login($sso_uid, 'SSO BYPASS')) {
      $this->log(\OCP\Util::ERROR,
                  sprintf('login(%s) failed; refer to previous logs'));

      return FALSE;
    }

    if (isset($_GET['redirect_url'])) {
      $part = explode('?', $_GET['redirect_url'], 2);
      $path = OC::$WEBROOT . $part[0];

      if (isset($part[1]))
        $path .= OC::$WEBROOT . '?' . urlencode($part[1]);

      OCP\Response::redirect($path);

      exit();
    }

    return TRUE;
  }
}
