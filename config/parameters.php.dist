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

\OCP\App::checkAppEnabled(basename(dirname(dirname(__FILE__))));

$config = array(
  'sso_url'             => 'FALSE',
  'slo_url'             => 'FALSE',

  'autocreate'          => 'TRUE',
  'update_user_data'    => 'TRUE',
  'user_deletion'       => 'FALSE',
  'user_change_avatar'  => 'FALSE',

  'base_directory'      => 'FALSE',

  'uid'                 => '$_SERVER[\'REMOTE_USER\']',
  'displayname'         => '$_SERVER[\'REMOTE_USER_CN\']',
  'email'               => '$_SERVER[\'REMOTE_USER_EMAIL\']',
  'avatar'              => 'FALSE',
);
