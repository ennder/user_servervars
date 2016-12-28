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

$app = basename(dirname(__FILE__));

\OCP\App::checkAppEnabled($app);

$name = strtoupper(preg_replace('/^user_/i', '', $app));

# Uncomment next line to deny modifications from OC interface
# define($name . '_RO_BINDING', TRUE);

\OCP\User::checkAdminUser();

require $app . '/config/parameters.php';

if ($_POST && !defined($name . '_RO_BINDING')) {
  \OCP\Util::callCheck();

  foreach ($config as $key => $default_value) {
    if (isset($_POST[$key])) {
      \OCP\Config::setAppValue($app, $key, $_POST[$key]);
    }
  }
}

$template = new OC_Template($app, 'settings');
foreach ($config as $key => $default_value) {
  $value = \OCP\Config::getAppValue($app, $key, $default_value);
  $value = htmlentities($value);
  $template->assign($key, $value);
}

return $template->fetchPage();
