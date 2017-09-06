<?php
set_time_limit(PHP_INT_MAX);

require_once 'config.php';
require_once 'common.php';

//DB table process id
$process_id = 2;

try {
    $con = startDBcon();

    //Verify Start or Continue Process
    $results = query($con, "SELECT id FROM execution WHERE status=0 AND process_id=$process_id;");
    if ($results->num_rows == 0) {
        //Start Process
        $exec_id = startProcess($con, $process_id);
        query($con, "UPDATE league SET status=0;");
        query($con, "UPDATE seniorteam SET active=0;");
        query($con, "UPDATE youthteam SET active=0,status=0;");
    } else {
        //Continue Process
        $exec_id = $results->fetch_assoc()['id'];
    }

    $url_base = 'http://' . $_SERVER['HTTP_HOST'] . '/EGO/updateTeam.php';

    $status = 1;
    $int = 0;
    $leagues = array();
    //For each league get seniorTeam and youthTeam
    $results = query($con, "SELECT id FROM league WHERE status=0");
    while ($row = $results->fetch_assoc()) {
        $int++;
        $leagues[] = $row['id'];
        if ($int % PARALLEL_THREADS == 0 || $int == $results->num_rows) {
            $leagues_count = count($leagues);
            $curl_arr = array();
            $master = curl_multi_init();
            for ($i = 0; $i < $leagues_count; $i++) {
                $url = $url_base . "?exec_id=" . $exec_id . "&league_id=" . $leagues[$i];
                $curl_arr[$i] = curl_init($url);
                curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
                curl_multi_add_handle($master, $curl_arr[$i]);
            }
            do {
                curl_multi_exec($master, $running);
            } while ($running > 0);
            if ($status == 1) {
                for ($i = 0; $i < $leagues_count; $i++) {
                    $res = curl_multi_getcontent($curl_arr[$i]);
                    if ($res != 'OK') {
                        $status = 0;
                        break;
                    }
                }
            }
            $leagues = array();
        }
    }
    $results = query($con, "SELECT count(*) AS 'total' FROM youthteam WHERE active=1")->fetch_assoc()['total'];
    endProcess($con, $exec_id, $status, $results);
    endDBcon($con);
} catch (\Exception $e) {
    echo $e->getMessage();
    login($config, $e->getMessage());
}