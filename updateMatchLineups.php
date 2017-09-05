<?php
set_time_limit(PHP_INT_MAX);

require_once 'config.php';
require_once 'common.php';

try {
    $HT = new \PHT\PHT($config);

    $process_id = 3;
    $con = startDBcon();

    //Verify Start or Continue Process
    $results = query($con, "SELECT id,params FROM execution WHERE status=0 AND process_id=$process_id;");
    if ($results->num_rows == 0) {
        //Start Process
        $params = array('startDate' => date("Y-m-d", strtotime("1 August 2017")), 'endDate' => date("Y-m-d"));
        $exec_id = startProcess($con, $process_id, http_build_query($params));
        query($con, "UPDATE youthTeam SET status=0;");
    } else {
        //Continue Process
        $row = $results->fetch_assoc();
        $exec_id = $row['id'];
        parse_str($row['params'], $params);
    }

    //For each youthTeam get youthMatches and youthLineups
    $results = query($con, "SELECT id FROM youthTeam WHERE active=1 AND status=0");
    while ($row = $results->fetch_assoc()) {
        $youthTeam_id = $row['id'];
        $matches = $HT->getYouthMatchesArchive($youthTeam_id, $params['startDate'], $params['endDate'])->getMatches();
        foreach ($matches as $match) {
            $youthMatch_id = $match->getId();
            $youthMatch_date = $match->getDate();
            $youthMatch_type = $match->getType();
            $youthMatch_HomeTeam_Id = $match->getHomeTeamId();
            $youthMatch_AwayTeam_Id = $match->getAwayTeamId();
            query($con, "INSERT INTO youthMatch(id, date, type, homeTeam_id, awayTeam_id) VALUES($youthMatch_id, '$youthMatch_date', $youthMatch_type, $youthMatch_HomeTeam_Id, $youthMatch_AwayTeam_Id);");
            $lineup = $HT->getYouthMatchLineup($youthMatch_id, $youthTeam_id);
            foreach ($lineup->getFinalPlayers() as $lineupPlayer) {
                $stars = (float)$lineupPlayer->getRatingStars();
                if ($stars < 5.0) continue;
                try {
                    $player = $lineupPlayer->getPlayer();
                } catch (exception $ex) {
                    continue;
                }
                $youthPlayer_id = $lineupPlayer->getId();
                $youthPlayer_first_name = addslashes($lineupPlayer->getFirstName());
                $youthPlayer_last_name = addslashes($lineupPlayer->getLastName());
                $youthPlayer_specialty = $player->getSpecialty();
                $youthPlayer_age = $player->getAge();
                $youthPlayer_days = $player->getDays();
                $youthPlayer_promotedIn = $player->getCanBePromotedIn();
                $query = "INSERT INTO youthPlayer(id, first_name, last_name, specialty, age, days, date, promotedIn, youthTeam_id) ";
                $query .= "VALUES($youthPlayer_id, '$youthPlayer_first_name', '$youthPlayer_last_name', $youthPlayer_specialty, $youthPlayer_age, $youthPlayer_days,CURTIME(), $youthPlayer_promotedIn, $youthTeam_id);";
                query($con, $query);
                $position = $lineupPlayer->getRole();
                $order = $lineupPlayer->getIndividualOrder();
                $order = $order == null ? "'null'" : $order;
                $query = "INSERT INTO youthMatchLineup(youthMatch_id, youthPlayer_id, position, `order`, stars) VALUES($youthMatch_id, $youthPlayer_id, $position, $order, $stars);";
                query($con, $query);
            }
        }
        query($con, "UPDATE youthTeam SET status=1 WHERE id=$youthTeam_id;");
        updateProcess($con, $exec_id);
    }

    $results = query($con, "SELECT count(*) AS 'total' FROM youthTeam WHERE active=1")->fetch_assoc()['total'];
    endProcess($con, $exec_id, 1, $results);
    endDBcon($con);
} catch (\Exception $e) {
    echo $e->getMessage();
    login($config, $e->getMessage());
}