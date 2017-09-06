<?php
set_time_limit(PHP_INT_MAX);

require_once 'config.php';
require_once 'common.php';

//DB table process id
$process_id = 3;

try {
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
        //TODO implement PARALLEL_THREADS
    }
    $results = query($con, "SELECT count(*) AS 'total' FROM youthTeam WHERE active=1")->fetch_assoc()['total'];
    endProcess($con, $exec_id, 1, $results);
    endDBcon($con);
} catch (\Exception $e) {
    echo $e->getMessage();
    login($config, $e->getMessage());
}