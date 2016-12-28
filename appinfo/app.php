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

$app = basename(dirname(dirname(__FILE__)));

\OCP\App::checkAppEnabled($app);

$uname = strtoupper(preg_replace('/^user_/i', '', $app));

# Activate backend
require_once OC_App::getAppPath($app). '/' . $app . '.php';

//2016/12/28 Jebat deprecated unuseful
//OC_User::registerBackend($uname);
OC_User::useBackend($uname);

# Attempt to login
if (isset($_GET['app']) && $_GET['app'] === $app) {
  $class = 'OC_' . strtoupper($app);
  $backend = new $class();
  if ($backend->login() === FALSE) {
    OC::$REQUESTEDAPP = '';
    OC_Util::redirectToDefaultPage();
  }
} else {
  # Add a button to default login page
  $path = OC::$WEBROOT . '/index.php?app=' . $app;
  $appLogin = array(
    'href' => $path,
    'name' => 'Login with Smile SSO',
  );
  OC_APP::registerLogin($appLogin);
  
  if (isset($_GET['redirect_url'])) {
    $path .= '&redirect_url=' . urlencode($_GET['redirect_url']);
    $appLogin = array(
      'href' => $path,
      'name' => 'Continue to ' . $_GET['redirect_url'] . ' with ' . $uname,
    );
    OC_APP::registerLogin($appLogin);
  }
}

# Add app settings page to OC interface
OC_APP::registerAdmin($app, 'settings');

$entry = array(
  'id'    => $app . '_settings',
  'order' => 1,
  //2016/12/28 Jebat Owncloud > 8
  'href'  => \OC::$server->getURLGenerator()->linkTo($app, 'settings.php'),
  //'href'  => OC_Helper::linkTo($app, 'settings.php'),
  'name'  => $uname
);
