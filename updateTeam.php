<?php
set_time_limit(PHP_INT_MAX);

require_once 'config.php';
require_once 'common.php';

try {
    $HT = new \PHT\PHT($config);

    $league_id = $_GET["league_id"];
    $exec_id = $_GET["exec_id"];

    $con = startDBcon();

    //Collect BD seniorTeam_id for insert or update
    $seniorTeams = array();
    $results = query($con, "SELECT id FROM seniorteam");
    while ($row = $results->fetch_assoc()) {
        $seniorTeams[] = $row['id'];
    }

    //Collect BD youthTeam_id for insert or update
    $youthTeams = array();
    $results = query($con, "SELECT id FROM youthteam");
    while ($row = $results->fetch_assoc()) {
        $youthTeams[] = $row['id'];
    }

    $league = $HT->getSeniorLeague($league_id);
    foreach ($league->getTeams() as $team) {
        $seniorTeam = $team->getTeam();
        $seniorTeam_id = $seniorTeam->getId();
        if (!$seniorTeam->isBot()) {
            $seniorTeam_name = addslashes($seniorTeam->getName());
            $user_id = $seniorTeam->getUserId();
            if (in_array($seniorTeam_id, $seniorTeams)) {
                query($con, "UPDATE seniorteam SET name='$seniorTeam_name', user_id=$user_id, league_id=$league_id, active=1 WHERE id=$seniorTeam_id;");
            } else {
                query($con, "INSERT INTO seniorteam(id, name, user_id, league_id, active) VALUES($seniorTeam_id, '$seniorTeam_name', $user_id, $league_id, 1);");
            }
            $youthTeam = $seniorTeam->getYouthTeam();
            if (!$youthTeam == null) {
                $youthTeam_id = $youthTeam->getId();
                $youthTeam_name = addslashes($youthTeam->getName());
                if (in_array($youthTeam_id, $youthTeams)) {
                    query($con, "UPDATE youthteam SET name='$youthTeam_name', seniorTeam_id=$seniorTeam_id, active=1 WHERE id=$youthTeam_id;");
                } else {
                    query($con, "INSERT INTO youthteam(id, name, seniorTeam_id, active) VALUES($youthTeam_id, '$youthTeam_name', $seniorTeam_id, 1);");
                }
            }
        }
    }
    query($con, "UPDATE league SET status=1 WHERE id=$league_id;");
    updateProcess($con, $exec_id);
    echo 'OK';
} catch (\Exception $e) {
    echo $e->getMessage();
    login($config, $e->getMessage());
}