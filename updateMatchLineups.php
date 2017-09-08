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
        $params = array('startDate' => date("Y-m-d", strtotime("-1 week")), 'endDate' => date("Y-m-d"));
        $exec_id = startProcess($con, $process_id, http_build_query($params));
        query($con, "UPDATE youthTeam SET status=0;");
    } else {
        //Continue Process
        $row = $results->fetch_assoc();
        $exec_id = $row['id'];
        parse_str($row['params'], $params);
    }

    $url_base = 'http://' . $_SERVER['HTTP_HOST'] . '/EGO/updateMatchLineup.php';

    $status = 1;
    $int = 0;
    $youthTeams = array();

    //For each youthTeam get youthMatches and youthLineups
    $results = query($con, "SELECT id FROM youthteam WHERE active=1 AND status=0");
    while ($row = $results->fetch_assoc()) {
        $int++;
        $youthTeams[] = $row['id'];
        if ($int % PARALLEL_THREADS == 0 || $int == $results->num_rows) {
            $youthTeams_count = count($youthTeams);
            $curl_arr = array();
            $master = curl_multi_init();
            for ($i = 0; $i < $youthTeams_count; $i++) {
                $url = $url_base . "?exec_id=" . $exec_id . "&youthTeam_id=" . $youthTeams[$i] . "&startDate=" . $params['startDate'] . "&endDate=" . $params['endDate'];
                $curl_arr[$i] = curl_init($url);
                curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
                curl_multi_add_handle($master, $curl_arr[$i]);
            }
            do {
                curl_multi_exec($master, $running);
            } while ($running > 0);
            if ($status == 1) {
                for ($i = 0; $i < $youthTeams_count; $i++) {
                    $res = curl_multi_getcontent($curl_arr[$i]);
                    if ($res != 'OK') {
                        $status = 0;
                        break;
                    }
                }
            }
            $youthTeams = array();
        }
    }
    $results = query($con, "SELECT count(*) AS 'total' FROM youthmatchlineup WHERE active=1")->fetch_assoc()['total'];
    endProcess($con, $exec_id, 1, $results);
    endDBcon($con);
} catch (\Exception $e) {
    echo $e->getMessage();
    login($config, $e->getMessage());
}