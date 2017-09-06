<?php

define("CHPP_CONSUMER_KEY", "XXX");
define("CHPP_CONSUMER_SECRET", "XXX");
define("CHPP_RETURN_URL", "XXX");

define("DB_HOST", "XXX");
define("DB_USER", "XXX");
define("DB_PWD", "XXX");
define("DB_NAME", "XXX");

define("FILENAME_OAUTH", "oauth.txt");
define("FILENAME_OAUTH_TOKEN", "oauthToken.txt");
define("FILENAME_OAUTH_TOKEN_SECRET", "oauthTokenSecret.txt");
define("ERROR_LOGIN", "401 - Unauthorized: Access is denied due to invalid credentials");

define("PARALLEL_THREADS", 40);

require_once 'PHT/autoload.php';

/*
https://github.com/jetwitaussi/PHT
CONSUMER_KEY           : your chpp app consumer key
CONSUMER_SECRET        : your chpp app consumer secret
OAUTH_TOKEN            : chpp user token
OAUTH_TOKEN_SECRET     : chpp user token secret
HT_SUPPORTER           : override ht supporter level, by default 0 (0=no change, -1=deactivate, 1=activate)
STARTING_INDEX         : used for loop, by default 0 (pht v2 has starting index at 1)
PROXY_IP               : set your proxy ip
PROXY_PORT             : set your proxy port
PROXY_USER             : set your proxy username
PROXY_PASSWORD         : set your proxy password
LOG_TYPE               : set log type, pht provides: 'file' and 'none', set a classname to use another logger
LOG_LEVEL              : set minimum level of log (see \PHT\Log\Level constants)
LOG_TIME               : set log time format (use php date() format)
LOG_FILE               : set log filename, prefer full path name
CACHE                  : set cache mechanism you want to use: 'none', 'apc', 'session', 'memory', 'memcached'. default: 'none'
CACHE_PREFIX           : set a prefix for cache key, default 'PHT_',
CACHE_TTL              : set a default ttl in seconds for caching xml request, default: 3600
MEMCACHED_SERVER_IP    : set ip of memcached server
MEMCACHED_SERVER_PORT  : set port of memcached server
*/

$config = array(
    'CONSUMER_KEY' => CHPP_CONSUMER_KEY,
    'CONSUMER_SECRET' => CHPP_CONSUMER_SECRET,
    'CACHE' => 'session',
    'CACHE_TTL' => 3000,
    'LOG_TYPE' => 'none',
    'LOG_LEVEL' => \PHT\Log\Level::DEBUG,
    'LOG_FILE' => __DIR__ . '/pht.log',
);

if (file_exists(FILENAME_OAUTH_TOKEN)) {
    $myFile = fopen(FILENAME_OAUTH_TOKEN, "r");
    $config['OAUTH_TOKEN'] = fread($myFile, filesize(FILENAME_OAUTH_TOKEN));
    fclose($myFile);
} else {
    $config['OAUTH_TOKEN'] = "";
}

if (file_exists(FILENAME_OAUTH_TOKEN_SECRET)) {
    $myFile = fopen(FILENAME_OAUTH_TOKEN_SECRET, "r");
    $config['OAUTH_TOKEN_SECRET'] = fread($myFile, filesize(FILENAME_OAUTH_TOKEN_SECRET));
    fclose($myFile);
} else {
    $config['OAUTH_TOKEN_SECRET'] = "";
}

if (!empty($_REQUEST['oauth_token'])) {
    $HT = new \PHT\Connection($config);

    $myFile = fopen(FILENAME_OAUTH, "r") or die("Unable to open file!");
    $tmpToken = fread($myFile, filesize(FILENAME_OAUTH));
    fclose($myFile);

    $access = $HT->getChppAccess($tmpToken, $_REQUEST['oauth_token'], $_REQUEST['oauth_verifier']);
    if ($access === false) {
        echo "Impossible to confirm chpp connection";
        exit();
    }

    $config['OAUTH_TOKEN'] = $access->oauthToken;
    $myFile = fopen(FILENAME_OAUTH_TOKEN, "w") or die("Unable to open file!");
    fwrite($myFile, $access->oauthToken);
    fclose($myFile);

    $config['OAUTH_TOKEN_SECRET'] = $access->oauthTokenSecret;
    $myFile = fopen(FILENAME_OAUTH_TOKEN_SECRET, "w") or die("Unable to open file!");
    fwrite($myFile, $access->oauthTokenSecret);
    fclose($myFile);
}

function login($config, $errorMessage)
{
    //$errorMessage contains ERROR_LOGIN
    if (strpos($errorMessage, ERROR_LOGIN) !== true) {
        $HT = new \PHT\Connection($config);
        $auth = $HT->getPermanentAuthorization(CHPP_RETURN_URL); // put your own url :)
        if ($auth === false) {
            echo "Impossible to initiate chpp connection";
            exit();
        }
        $myFile = fopen(FILENAME_OAUTH, "w") or die("Unable to open file!");
        fwrite($myFile, $auth->temporaryToken);
        fclose($myFile);
        header('Location: ' . $auth->url);
        exit();
    }
}