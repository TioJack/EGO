<?php
set_time_limit(PHP_INT_MAX);

require_once 'config.php';
require_once 'common.php';

try {
    $HT = new \PHT\PHT($config);

    $process_id = 2;
    $con = startDBcon();

    //Verify Start or Continue Process
    $results = query($con, "SELECT id FROM execution WHERE status=0 AND process_id=$process_id;");
    if ($results->num_rows == 0) {
        //Start Process
        $exec_id = startProcess($con, $process_id);
        query($con, "UPDATE league SET status=0;");
        query($con, "UPDATE seniorTeam SET active=0;");
        query($con, "UPDATE youthTeam SET active=0,status=0;");
    } else {
        //Continue Process
        $exec_id = $results->fetch_assoc()['id'];
    }

    //Collect BD seniorTeam_id for insert or update
    $seniorTeams = array();
    $results = query($con, "SELECT id FROM seniorTeam");
    while ($row = $results->fetch_assoc()) {
        $array[] = $row['id'];
    }

    //Collect BD youthTeam_id for insert or update
    $youthTeams = array();
    $results = query($con, "SELECT id FROM youthTeam");
    while ($row = $results->fetch_assoc()) {
        $array[] = $row['id'];
    }

    //For each league get seniorTeam and youthTeam
    $results = query($con, "SELECT id FROM league WHERE status=0");
    while ($row = $results->fetch_assoc()) {
        $league_id = $row['id'];
        $league = $HT->getSeniorLeague($league_id);
        foreach ($league->getTeams() as $team) {
            $seniorTeam = $team->getTeam();
            $seniorTeam_id = $seniorTeam->getId();
            if (!$seniorTeam->isBot()) {
                $seniorTeam_name = addslashes($seniorTeam->getName());
                $user_id = $seniorTeam->getUserId();
                if (in_array($seniorTeam_id, $seniorTeams)) {
                    query($con, "UPDATE seniorTeam SET name='$seniorTeam_name', user_id=$user_id, league_id=$league_id, active=1 WHERE id=$seniorTeam_id;");
                } else {
                    query($con, "INSERT INTO seniorTeam(id, name, user_id, league_id, active) VALUES($seniorTeam_id, '$seniorTeam_name', $user_id, $league_id, 1);");
                }
                $youthTeam = $seniorTeam->getYouthTeam();
                if (!$youthTeam == null) {
                    $youthTeam_id = $youthTeam->getId();
                    $youthTeam_name = addslashes($youthTeam->getName());
                    if (in_array($seniorTeam_id, $youthTeams)) {
                        query($con, "UPDATE youthTeam SET name='$seniorTeam_name', seniorTeam_id=$seniorTeam_id, active=1 WHERE id=$youthTeam_id;");
                    } else {
                        query($con, "INSERT INTO youthTeam(id, name, seniorTeam_id, active) VALUES($youthTeam_id, '$youthTeam_name', $seniorTeam_id, 1);");
                    }
                }
            }
        }
        query($con, "UPDATE league SET status=1 WHERE id=$league_id;");
        updateProcess($con, $exec_id);
    }

    $results = query($con, "SELECT count(*) AS 'total' FROM youthTeam WHERE active=1")->fetch_assoc()['total'];
    endProcess($con, $exec_id, 1, $results);
    endDBcon($con);
} catch (\Exception $e) {
    echo $e->getMessage();
    login($config, $e->getMessage());
}