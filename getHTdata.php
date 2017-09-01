<?php
set_time_limit(120);

use PHT\Config\Search;

require_once 'config.php';
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

$filename_oauth = "oauth.txt";
$filename_oauthToken = "oauthToken.txt";
$filename_oauthTokenSecret = "oauthTokenSecret.txt";

if (file_exists($filename_oauthToken)) {
    $myfile = fopen($filename_oauthToken, "r");
    $config['OAUTH_TOKEN'] = fread($myfile, filesize($filename_oauthToken));
    fclose($myfile);
} else {
    $config['OAUTH_TOKEN'] = "";
}

if (file_exists($filename_oauthTokenSecret)) {
    $myfile = fopen($filename_oauthTokenSecret, "r");
    $config['OAUTH_TOKEN_SECRET'] = fread($myfile, filesize($filename_oauthTokenSecret));
    fclose($myfile);
} else {
    $config['OAUTH_TOKEN_SECRET'] = "";
}

if (!empty($_REQUEST['oauth_token'])) {
    $HT = new \PHT\Connection($config);

    $myfile = fopen($filename_oauth, "r") or die("Unable to open file!");
    $tmpToken = fread($myfile, filesize($filename_oauth));
    fclose($myfile);

    $access = $HT->getChppAccess($tmpToken, $_REQUEST['oauth_token'], $_REQUEST['oauth_verifier']);
    if ($access === false) {
        echo "Impossible to confirm chpp connection";
        exit();
    }

    $config['OAUTH_TOKEN'] = $access->oauthToken;
    $myfile = fopen($filename_oauthToken, "w") or die("Unable to open file!");
    fwrite($myfile, $access->oauthToken);
    fclose($myfile);

    $config['OAUTH_TOKEN_SECRET'] = $access->oauthTokenSecret;
    $myfile = fopen($filename_oauthTokenSecret, "w") or die("Unable to open file!");
    fwrite($myfile, $access->oauthTokenSecret);
    fclose($myfile);
}

try {
    $HT = new \PHT\PHT($config);

    $con = mysqli_connect(DB_HOST, DB_USER, DB_PWD, DB_NAME);
    if (!$con) {
        die("Impossible database connection: " . mysqli_error($con));
    }

    $leagues = array("Primera", "II.", "III.", "IV.", "V.", "VI.", "VII.", "VIII.");

    $searchParam = new Search();
    $searchParam->countryLeagueId = 36; //Spain

    for ($l = 0; $l < count($leagues); $l++) {
        $searchParam->seniorLeagueName = $leagues[$l];
        $searchParam->page = 0;
        $res = $HT->search($searchParam);
        processSearchResponse($res);
        $totalPage = (int)$res->getTotalPage();
        for ($p = 1; $p < $totalPage; $p++) {
            $searchParam->page = $p;
            $res = $HT->search($searchParam);
            processSearchResponse($res);
        }
    }

//    $match = $HT->getYouthMatch(102005389);
//    echo $match->getXmlText();
//    $homeTeam = $match->getHomeTeam();
//    echo $homeTeam->getXmlText();
//    $team = $homeTeam->getId();
//    $lineup = $homeTeam->getLineup();
//    echo $lineup->getXmlText();
//
//    foreach ($lineup->getStartingPlayers() as $plyr) {
//        $player = $plyr->getPlayer();
//
//        $id = $player->getId();
//        $first_name = $player->getFirstName();
//        $last_name = $player->getLastName();
//        $specialty = $player->getSpecialty();
//
//        $result = mysqli_query($con, "INSERT INTO player(id,first_name,last_name,specialty,team) VALUES ($id,'$first_name','$last_name',$specialty,$team);");
//        if (!$result) {
//            echo mysqli_error($con);
//        }
//    }

    mysqli_close($con);

} catch (\PHT\Exception\ChppException $e) {
    echo $e->getMessage();
    $HT = new \PHT\Connection($config);
    $auth = $HT->getPermanentAuthorization(CHPP_RETURN_URL); // put your own url :)
    if ($auth === false) {
        echo "Impossible to initiate chpp connection";
        exit();
    }
    $myfile = fopen($filename_oauth, "w") or die("Unable to open file!");
    fwrite($myfile, $auth->temporaryToken);
    fclose($myfile);
    header('Location: ' . $auth->url);
    exit();
} catch (\PHT\Exception\NetworkException $e) {
    echo $e->getMessage();
}

function processSearchResponse(PHT\Xml\Search\Response $res){
    foreach ($res->getResults() as $result) {
        echo $result->getValue().' '.$result->getId().'<br>';
    }
}
?>