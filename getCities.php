<?php

require 'config/config.php';

$db = mysqli_connect($dbConfig['host'],$dbConfig['user'],$dbConfig['pass'],$dbConfig['database']);
if (!$db) {
    die('Could not connect: ' . mysql_error());
}



//query for all of users friends
$query = "
        SELECT *
        FROM cities
        ";
        
$result = $db->query($query);

$cities = array();
while($row = mysqli_fetch_assoc($result)) {
    $cities[] = $row;
}

//print_r($cities);


//
//print_r($json);
//
$db->close();
echo json_encode($cities);

?>