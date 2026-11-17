<?php
/** OAuth 2.0 authorization-code providers. A provider is offered only when OAUTH_{NAME}_CLIENT_ID and _CLIENT_SECRET are set in .env.
 *  Callback URL to register at the provider: {APP_URL}/account/social/{provider}/callback/ */
return [
    'google' => [
        'label' => 'Google', 'auth' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token',
        'userinfo' => 'https://openidconnect.googleapis.com/v1/userinfo', 'scope' => 'openid email profile',
    ],
    'github' => [
        'label' => 'GitHub', 'auth' => 'https://github.com/login/oauth/authorize', 'token' => 'https://github.com/login/oauth/access_token',
        'userinfo' => 'https://api.github.com/user', 'emails' => 'https://api.github.com/user/emails', 'scope' => 'read:user user:email',
    ],
    'facebook' => [
        'label' => 'Facebook', 'auth' => 'https://www.facebook.com/v19.0/dialog/oauth', 'token' => 'https://graph.facebook.com/v19.0/oauth/access_token',
        'userinfo' => 'https://graph.facebook.com/me?fields=id,name,email', 'scope' => 'email,public_profile',
    ],
    'linkedin' => [
        'label' => 'LinkedIn', 'auth' => 'https://www.linkedin.com/oauth/v2/authorization', 'token' => 'https://www.linkedin.com/oauth/v2/accessToken',
        'userinfo' => 'https://api.linkedin.com/v2/userinfo', 'scope' => 'openid profile email',
    ],
];
