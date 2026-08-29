<?php
// The mediawiki.org OAuth 2.0 consumer secret lives in secrets-oauth.php, which is
// git-crypt-encrypted (see .gitattributes) so it is not stored in plaintext in git.
require_once __DIR__ . '/secrets-oauth.php';

// mediawiki.org OAuth login via WSOAuth (PluggableAuth client).
// Keep local password login as a fallback (safe default).
$wgPluggableAuth_EnableLocalLogin = true;

// Allow PluggableAuth/WSOAuth to auto-create a local account from the mediawiki.org
// OAuth identity. autocreateaccount alone was insufficient in this flow, so createaccount
// is also granted to *. CAVEAT: this also permits open self-registration — revisit by
// disabling the CreateAccount special page once SSO login is confirmed working.
$wgGroupPermissions['*']['autocreateaccount'] = true;
$wgGroupPermissions['*']['createaccount'] = true;

// Register the custom OAuth 2.0 provider for mediawiki.org (WSOAuth's built-in
// MediaWikiAuth is OAuth 1.0a-only; this adds OAuth 2.0). The provider class lives in
// user-extensions (gitops-persisted) rather than inside the WSOAuth extension, so it
// survives image rebuilds; autoload it explicitly. Both providers share the same
// Special:PluggableAuthLogin return endpoint and are selected by which login button
// (config entry) triggered the flow.
$wgAutoloadClasses['Mwstake\OAuth2\MediaWikiOAuth2'] = '/var/www/mediawiki/w/user-extensions/MediaWikiOAuth2/MediaWikiOAuth2.php';
$wgOAuthCustomAuthProviders = [
    "mediawiki2" => \Mwstake\OAuth2\MediaWikiOAuth2::class,
];

// Consumers differ per wiki; select by HTTP_HOST (reliable during the web request).
$host = $_SERVER["HTTP_HOST"] ?? "";
$mwOAuthConsumers = [
    // mediawiki.org OAuth 2.0 consumer (key 58d2f224…). Callback registered at
    // https://meta.wikiapiary.com/wiki/Special:PluggableAuthLogin
    "meta.wikiapiary.com" => [ "58d2f224fbbfefcfad74ee7e30787efe", $wgOAuthMediaWikiClientSecret ],
    "dev.mwstake.org"     => [ "", "" ], // TODO: add dev.mwstake.org consumer key/secret
];
$consumer = $mwOAuthConsumers[ $host ] ?? [ "", "" ];
if ( $consumer[0] !== "" ) {
    $wgPluggableAuth_Config["mediawiki2"] = [
        "plugin" => "WSOAuth",
        "data" => [
            "type" => "mediawiki2",
            "clientId" => $consumer[0],
            "clientSecret" => $consumer[1],
            "redirectUri" => "https://" . $host . "/wiki/Special:PluggableAuthLogin",
        ],
    ];
}
