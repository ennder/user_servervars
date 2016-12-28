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

$uapp = strtoupper($app);
$uname = preg_replace('/^user_/i', '', $uapp);

$settings = array(

  'Basic settings' => array(

    'sso_url'
      => 'SSO URL to redirect to on unauthenticated user',

    'slo_url'
      => 'SLO URL to redirect to on user logout',

    'autocreate'
      => 'Import user to backend table on successful authentication',

    'update_user_data'
      => 'Always update user data (display name, email, avatar) after login',

    'user_deletion'
      => 'Enable user deletion in OC (will not delete user in SSO backend)',

    'user_change_avatar'
      => 'Allow user to modify his avatar file',

  ),

  'Intermediate settings' => array(

    'base_directory'
      => 'Base directory where users home directories are located',

  ),

  'Mapping' => array(

    'uid'
      => 'User ID php expression',

    'displayname'
      => 'Display name php expression',

    'email'
      => 'Email php expression',

    'avatar'
      => 'Avatar php expression',

  ),
);
?>

<div class="section">

  <form id="<?php p($app) ?>" action="#" method="post">

    <h2><?php
      p($l->t($uapp))
    ?></h2>

    <p><strong><?php
        p($l->t("$uname Authentication and Provisioning backend"))
      ?></strong></p>

    <p>The following settings are *all* evaluated as PHP expressions. These may
    reference superglobals ($_SERVER, $_ENV...) and the $uid variable, which may
    be available in some contexts.</p>

    <?php foreach ($settings as $legend => $params): ?>
    <fieldset>

      <legend><?php
        if (defined($uname . '_RO_BINDING')) {
          p($l->t($legend . ' (Read-Only)'));
        }
        else {
          p($l->t($legend));
        }
      ?></legend>

      <?php foreach ($params as $p_name => $p_label): ?>
      <label for="<?php p($p_name) ?>"><?php
        p($l->t($p_label))
      ?></label>

      <input type="text" id="<?php p($p_name) ?>" name="<?php p($p_name) ?>"
        <?php if (defined($uname . '_RO_BINDING')) p('readonly="readonly"') ?>
        value="<?php p($_[$p_name]) ?>">

      <br />
      <?php endforeach ?>

    </fieldset>
    <?php endforeach ?>

    <input type="hidden" id="requesttoken" name="requesttoken"
      value="<?php p($_['requesttoken']) ?>">

    <input type="submit" value="<?php p($l->t('Save')) ?>" />

  </form>

</div>
