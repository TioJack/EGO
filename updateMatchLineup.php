<?php
set_time_limit(PHP_INT_MAX);

require_once 'config.php';
require_once 'common.php';

try {
    $HT = new \PHT\PHT($config);

    $youthTeam_id = $_GET["youthTeam_id"];
    $startDate = $_GET["startDate"];
    $endDate = $_GET["endDate"];
    $exec_id = $_GET["exec_id"];

    $con = startDBcon();

    $matches = $HT->getYouthMatchesArchive($youthTeam_id, $startDate, $endDate)->getMatches();
    foreach ($matches as $match) {
        $saveMatch = false;
        $youthMatch_id = $match->getId();
        $youthMatch_date = $match->getDate();
        $lineup = $HT->getYouthMatchLineup($youthMatch_id, $youthTeam_id);
        foreach ($lineup->getFinalPlayers() as $lineupPlayer) {

            //Verify if exist youth player (dismissed or promoted)
            try {
                $player = $lineupPlayer->getPlayer();
            } catch (exception $ex) {
                continue;
            }

            $youthPlayer_age = $player->getAge();
            $youthPlayer_days = $player->getDays();
            $position = $lineupPlayer->getRole();
            $stars = (float)$lineupPlayer->getRatingStars();

            //Discard not best players
            if (!isBestPlayer($youthPlayer_age, $youthPlayer_days, $youthMatch_date, $position, $stars)) continue;

            $youthPlayer_id = $lineupPlayer->getId();
            $youthPlayer_first_name = addslashes($lineupPlayer->getFirstName());
            $youthPlayer_last_name = addslashes($lineupPlayer->getLastName());
            $youthPlayer_specialty = $player->getSpecialty();
            $youthPlayer_promotedIn = $player->getCanBePromotedIn();
            $query = "INSERT INTO youthPlayer(id, first_name, last_name, specialty, age, days, date, promotedIn, youthTeam_id) ";
            $query .= "VALUES($youthPlayer_id, '$youthPlayer_first_name', '$youthPlayer_last_name', $youthPlayer_specialty, $youthPlayer_age, $youthPlayer_days,CURTIME(), $youthPlayer_promotedIn, $youthTeam_id);";
            query($con, $query);
            $order = $lineupPlayer->getIndividualOrder();
            $order = $order == null ? "NULL" : $order;
            $query = "INSERT INTO youthMatchLineup(youthMatch_id, youthPlayer_id, position, `order`, stars) VALUES($youthMatch_id, $youthPlayer_id, $position, $order, $stars);";
            query($con, $query);
            $saveMatch = true;
        }
        if ($saveMatch) {
            $youthMatch_type = $match->getType();
            $youthMatch_HomeTeam_Id = $match->getHomeTeamId();
            $youthMatch_AwayTeam_Id = $match->getAwayTeamId();
            query($con, "INSERT INTO youthMatch(id, date, type, homeTeam_id, awayTeam_id) VALUES($youthMatch_id, '$youthMatch_date', $youthMatch_type, $youthMatch_HomeTeam_Id, $youthMatch_AwayTeam_Id);");
        }
    }
    query($con, "UPDATE youthTeam SET status=1 WHERE id=$youthTeam_id;");
    updateProcess($con, $exec_id);
    echo 'OK';
} catch (\Exception $e) {
    echo $e->getMessage();
    login($config, $e->getMessage());
}

function isBestPlayer($age, $days, $youthMatch_date, $position, $stars)
{
    //$age and $days are actual. Is necessary calculate player's age on match date.
    $diff_days = date_diff(new DateTime(), DateTime::createFromFormat('Y-m-d H:i:s', $youthMatch_date))->format('%a');
    if ($days < $diff_days) {
        $age--;
    }
    if ($age > 16) {
        return false;
    }

    $position = role2position($position);
    //matrix minimal stars [age][position]
    $minimal[15][1] = 5.5;
    $minimal[15][2] = 5.5;
    $minimal[15][3] = 6.0;
    $minimal[15][4] = 6.0;
    $minimal[15][5] = 6.5;
    $minimal[15][6] = 7.5;
    $minimal[15][7] = 5.5;
    $minimal[16][1] = 6.5;
    $minimal[16][2] = 6.5;
    $minimal[16][3] = 6.5;
    $minimal[16][4] = 7.0;
    $minimal[16][5] = 7.0;
    $minimal[16][6] = 8.5;
    $minimal[16][7] = 6.5;

    return $minimal[$age][$position] <= $stars;
}

// Convert hattrick MatchRoleID to friendly position
//
// INPUT [MatchRoleID]
// http://hattrick.org/goto.ashx?path=/Community/CHPP/NewDocs/DataTypes.aspx#matchRoleID
// Value Description
// 100  Keeper
// 101	Right back
// 102	Right central defender
// 103	Middle central defender
// 104	Left central defender
// 105	Left back
// 106	Right winger
// 107	Right inner midfield
// 108	Middle inner midfield
// 109	Left inner midfield
// 110	Left winger
// 111	Right forward
// 112	Middle forward
// 113	Left forward
// 114	Substitution (Keeper)
// 115	Substitution (Defender)
// 116	Substitution (Inner midfield)
// 117	Substitution (Winger)
// 118	Substitution (Forward)
// 200	Substitution (Keeper)
// 201	Substitution (Central defender)
// 202	Substitution (Wing back)
// 203	Substitution (Inner midfielder)
// 204	Substitution (Forward)
// 205	Substitution (Winger)
// 206	Substitution (Extra)
// 207	Backup (Keeper)
// 208	Backup (Central defender)
// 209	Backup (Wing back)
// 210	Backup (Inner midfielder)
// 211	Backup (Forward)
// 212	Backup (Winger)
// 213	Backup (Extra)
// 17	Set pieces
// 18	Captain
// 19	Replaced Player #1
// 20	Replaced Player #2
// 21	Replaced Player #3
// 22	Penalty taker (1)
// 23	Penalty taker (2)
// 24	Penalty taker (3)
// 25	Penalty taker (4)
// 26	Penalty taker (5)
// 27	Penalty taker (6)
// 28	Penalty taker (7)
// 29	Penalty taker (8)
// 30	Penalty taker (9)
// 31	Penalty taker (10)
// 32	Penalty taker (11)
//
// OUTPUT [friendly position]
// Value Description
// 1 Keeper
// 2 Lateral Defender
// 3 Central Defender
// 4 Winger
// 5 Inner Midfield
// 6 Forward
// 7 Other
function role2position($role)
{
    switch ($role) {
        case 100:
            return 1;
            break;
        case 101:
        case 105:
            return 2;
            break;
        case 102:
        case 103:
        case 104:
            return 3;
            break;
        case 106:
        case 110:
            return 4;
            break;
        case 107:
        case 108:
        case 109:
            return 5;
            break;
        case 111:
        case 112:
        case 113:
            return 6;
            break;
        default:
            return 7;
            break;
    }
}