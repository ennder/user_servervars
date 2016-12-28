INTRODUCTION
============

This App provides authentication and provisioning support based on HTTP
server environment variables access through PHP and, concomitantly, on
HTTP authentication if needed.

ACKNOWLEDGMENT
==============

This App heavilly and shamelessly used to dwell on the user_saml App by
Sixto Martin (Yaco Sistemas // CONFIA). Funny and / or foolish code
parts were my own responsibility and this did not improve with time
going.

The login option for OC main login page was pointed with relevant code
by Gilian Gamb (CNRS).



INSTALLATION
============

PREVIOUS DEPENDENCE
-------------------

This App has no library dependence beside those normaly required for OC.
However it requires proper HTTP server configuration and knowledge of
parts of its configuration.

STEPS
-----

1. Copy the 'user_servervars' folder inside the ownCloud's apps folder
   and give to apache server read access privileges on the whole folder.
2. Access to ownCloud web with a user with admin privileges.
3. Access to the Applications panel and enable the SERVERVARS app.
4. Access to the Administration panel and configure the SERVERVARS app.
5. Login through this App is unobstrusively (as opposed by OC default
   login page) handled at the URL /?app=user_servervars ; it may be
   suitable to add a redirection from root page to this URL.

Alternatively to above steps 2 to 4, one may directly update desired
mappings in config/parameters.php and enforce strict readonly access in
OC's admin panel by defining 'SERVERVARS_RO_BINDING' in settings.php.

EXTRA INFO
==========

* The "External URL to redirect to for authentication" parameter
  redirects user if he cannot be identified.

* The "External URL to redirect to on logout" parameter redirects user
  after his session on owncloud has been cleared. This may be required
  on some SSO platforms to further dispose of sessions with the HTTP
  server itself.

* If you enable the "Autocreate user after ServerVars login" option,
  then if an
  user does not exist, he will be created. If this option is disabled
  and the user does not exist, then the user will be denied log in
  ownCloud.

* If you enable the "Update user data" option, when an existing user
  enters, his displayName, email and avatar will be updated.

The mapping section of configuration maps user's attributes with PHP
code which should evaluate to actual user specifics values. The intended
functionnality is to thus refer to superglobal variables which expand to
the actual values, but more general PHP code may be added here.

One may argue on security risks associated with the php expression
evaluation approach. Note that only an admin user has access to the App
parameters and may change the values to hazardous ones. Compare with the
ability for an admin user to install 3rd-party Apps. Anyway, one may
disable modifications on variables through the definition of
SERVERVARS_RO_BINDING in 'settings.php'. This can be done after correct
configuration of mapping from the config page.

Mappings are:
1. Login name: the unique identifier for the user
2. Display name: the human-compatible identifier for the user
3. Email variable: the user's mail commas and spaces)
4. Avatar: a base64 encoded jpg or png image, as would suit a web
   browser. There is an example in scripts directory.

* If you want to redirect to any specific app after login you
  may set the url param redirect_url, which should be url decoded to a
  valid path and query string starting from owncloud base URL. Ex:
  ?app=user_servervars&redirect_url=%2Findex.php%2Fapps%2Fgallery

NOTES
=====

servervars ? What sort of name is that ? I tried to find something less
misleading; php superglobals (user_psg) ?  This would not work out
either. So, many code parts are written to facilitate a possible
renaming of the whole stuff.
