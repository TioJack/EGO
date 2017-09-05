<?php
set_time_limit(1200); //20 minutes

require_once 'config.php';
require_once 'common.php';

try {
    $HT = new \PHT\PHT($config);

    $process_id = 1;
    $con = startDBcon();

    //Verify Start or Continue Process
    $results = query($con, "SELECT id FROM execution WHERE status=0 AND process_id=$process_id;");
    if ($results->num_rows == 0) {
        //Start Process
        $exec_id = startProcess($con, $process_id);
    } else {
        //Continue Process
        $exec_id = $results->fetch_assoc()['id'];
    }

    query($con, "TRUNCATE league;");

    //1 + 4 + 16 + 64 + 256 + 1024 + 1024 + 2048 = 4437
    $leagues = array("Primera", "II.", "III.", "IV.", "V.", "VI.", "VII.", "VIII.");

    $searchParam = new PHT\Config\Search();
    $searchParam->countryLeagueId = 36; //Spain

    for ($l = 0; $l < count($leagues); $l++) {
        $searchParam->seniorLeagueName = $leagues[$l];
        $searchParam->page = 0;
        $res = $HT->search($searchParam);
        processSearchResponse($con, $exec_id, $res);
        $totalPage = (int)$res->getTotalPage();
        for ($p = 1; $p < $totalPage; $p++) {
            $searchParam->page = $p;
            $res = $HT->search($searchParam);
            processSearchResponse($con, $exec_id, $res);
        }
    }

    $results = query($con, "SELECT count(*) AS 'total' FROM league")->fetch_assoc()['total'];
    endProcess($con, $exec_id, 1, $results);
    endDBcon($con);
} catch (Exception $e) {
    echo $e->getMessage();
    login($config, $e->getMessage());
}

function processSearchResponse($con, $exec_id, PHT\Xml\Search\Response $res)
{
    foreach ($res->getResults() as $result) {
        $id = $result->getId();
        $name = $result->getValue();
        query($con, "INSERT INTO league(id,name,status) VALUES ($id,'$name',0);");
        updateProcess($con, $exec_id);
    }
}

