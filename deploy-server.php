<?php

  include("../Functions.php");
  include("/var/www/vhosts/spillhandel.no/httpdocs/admin/domains/whois/WHOIS.php");
  include("/var/www/vhosts/spillhandel.no/httpdocs/admin/domains/db.php");
  exit();
  function getTld($domain){
    $tld = strrchr ($domain, "." );
    $tld = substr ( $tld, 1 );
    return $tld;
  }

  function generateRandom($min = 1, $max = 20) {
    if (function_exists('random_int')):
        return random_int($min, $max); // more secure
    elseif (function_exists('mt_rand')):
        return mt_rand($min, $max); // faster
    endif;
    return rand($min, $max); // old
  }

  function replaceServer($domain, $serverID){
    $snapshotID = get_snapshots();
    $serverName = strtoupper(generateRandomString(8));
    createServer($serverName, $snapshotID, $domain);
    sleep(generateRandom(1,5));
  }

  if(isset($_GET['replace'])){
    $domain = $_GET['domain'];
    $serverID = $_GET['serverID'];
    replaceServer($domain, $serverID);
    delete_servers($domain, $serverID);
    exit();
  }


  $dateTimeNow = date('Y-m-d H:i:s');
  $dateTimeNowUTC = gmdate("Y-m-d\TH:i:s\Z");
  $usedDateTime = '';

  global $domainConn;

  echo 'Local time:'.$dateTimeNow;
  echo '<br>';
  echo 'Server time:'.$dateTimeNowUTC;

  $sql = "SELECT * FROM `watchList` ORDER BY `expiryDate` ASC";
  $result = mysqli_query($domainConn, $sql);

  while($row = mysqli_fetch_assoc($result)) {

      $id = $row['id'];
      $domain = $row['domain'];
      $caught = $row['caught'];
      $bidding = $row['bidding'];
      $watchlist = $row['watchlist'];
      $excluded = $row['excluded'];
      $expiryDate = $row['expiryDate'];

      if (strpos('.nl', $domain)){
        $usedDateTime = $dateTimeNowUTC;
      }else{
        $usedDateTime = $dateTimeNow;
      }

      if ($bidding == '1' || $excluded == '1' || $watchlist == '0'){
        goto end_bidding;
      }

      if (($usedDateTime > $expiryDate)){
        echo '<br>';
        echo $domain;
        //exit();
        $sql = "UPDATE `watchList` SET `bidding` = '1' WHERE `watchList`.`id` = '$id';";
        mysqli_query($domainConn, $sql);

        $domainLTD = getTld($domain);

        $sql = "SELECT * FROM `domainConfiguration` WHERE `extension` LIKE '%".$domainLTD."%'";
        $result1 = mysqli_query($domainConn, $sql);
        while($row1 = mysqli_fetch_assoc($result1)) {
          $timeout = $row1['timeout'];
          $runEvery = $row1['runEvery'];
        }

        $servers = $timeout / $runEvery;
        echo '<br>';

        echo 'Servers: '.$servers;
        //exit();

        $snapshotID = get_snapshots();

        for($i = 1; $i <= $servers; $i++) {
          $serverName = strtoupper(generateRandomString(8));
          createServer($serverName, $snapshotID, $domain);
          sleep(generateRandom(1,5));
        }

        exit();

      }

      end_bidding:
      if ($caught == 1 && $bidding == 1 && $excluded == 0){
        $sql = "UPDATE `watchList` SET `excluded` = '1' WHERE `watchList`.`id` = '$id';";
        mysqli_query($domainConn, $sql);
        delete_servers($domain);
      }

   
  }


  function get_snapshots(){

    $crl = curl_init('https://api.hetzner.cloud/v1/images');
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($crl, CURLINFO_HEADER_OUT, true);

    curl_setopt($crl, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer HuREUnRBCFIFL4iEaYlM6xHkmP1fQgGgPqBtVYprDw5YC4zAEDj8a9fpfjZg6bPi',
    ));
    
    $result = curl_exec($crl);

    $items_c = substr_count($result, '"labels":');

    for( $i= 1 ; $i <= $items_c; $i++ ){
      $info = '{'.get_string_between($result, '{', '"labels":').'"labels":';
      $result = str_replace($info, '', $result);

      if (strpos($info, 'snapshot') !== false){
        $id = get_string_between($info, '"id": ', ',');
        return $id;
      }
    }

  }

  function delete_servers($domain, $serverID = ""){

    global $domainConn;

    $sql = "SELECT * FROM `servers` WHERE `domain` = '$domain'";
    $resultSQL = mysqli_query($domainConn, $sql);

    if (strlen($serverID) > 1){
      $crl = curl_init('https://api.hetzner.cloud/v1/servers/'.$serverID);
      curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($crl, CURLINFO_HEADER_OUT, true);
      curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "DELETE");

      curl_setopt($crl, CURLOPT_HTTPHEADER, array(
          'Authorization: Bearer HuREUnRBCFIFL4iEaYlM6xHkmP1fQgGgPqBtVYprDw5YC4zAEDj8a9fpfjZg6bPi',
      ));
    
      $result = curl_exec($crl);

      $sql = "DELETE FROM `servers` WHERE `serverID` = '$serverID'";
      mysqli_query($domainConn, $sql);
      return "";
    }

    while($row = mysqli_fetch_assoc($resultSQL)) {
      $serverID = $row['serverID'];

      $crl = curl_init('https://api.hetzner.cloud/v1/servers/'.$serverID);
      curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($crl, CURLINFO_HEADER_OUT, true);
      curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "DELETE");

      curl_setopt($crl, CURLOPT_HTTPHEADER, array(
          'Authorization: Bearer HuREUnRBCFIFL4iEaYlM6xHkmP1fQgGgPqBtVYprDw5YC4zAEDj8a9fpfjZg6bPi',
      ));
    
      $result = curl_exec($crl);

      $sql = "DELETE FROM `servers` WHERE `serverID` = '$serverID'";
      mysqli_query($domainConn, $sql);

    }

  }

  function createServer($serverName, $snapshotID, $domain){

    global $domainConn;

    //$crl = curl_init('https://api.hetzner.cloud/v1/datacenters');
    //curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($crl, CURLINFO_HEADER_OUT, true);

    //curl_setopt($crl, CURLOPT_HTTPHEADER, array(
    //  'Authorization: Bearer HuREUnRBCFIFL4iEaYlM6xHkmP1fQgGgPqBtVYprDw5YC4zAEDj8a9fpfjZg6bPi',
    //));

    //$result = curl_exec($crl);

    //echo $result;

    //exit();



    $serverTypes = array('nbg1', 'fsn1', 'hel1');
    $randIndex = array_rand($serverTypes);
    $serverLocation = $serverTypes[$randIndex];

    $post_data = '{"name": "'.$serverName.'","server_type": "cx11","location": "'.$serverLocation.'","start_after_create": true,"image": "'.$snapshotID.'"}';
  
    echo $post_data;

    $crl = curl_init('https://api.hetzner.cloud/v1/servers');
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($crl, CURLINFO_HEADER_OUT, true);
    curl_setopt($crl, CURLOPT_POST, true);
    curl_setopt($crl, CURLOPT_POSTFIELDS, $post_data);

    curl_setopt($crl, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer HuREUnRBCFIFL4iEaYlM6xHkmP1fQgGgPqBtVYprDw5YC4zAEDj8a9fpfjZg6bPi',
    ));
    
    $result = curl_exec($crl);
   
    $ServerID = get_string_between($result, '"id": ', ',');
    $rootPassword = get_string_between($result, '"root_password": "', '"');
    echo $result;
    curl_close($crl);

    $sql = "INSERT INTO `servers` (`serverID`, `rootPassword`, `hostName`, `domain`) VALUES ('$ServerID', '$rootPassword', '$serverName', '$domain');";
    mysqli_query($domainConn, $sql);

  }

  //delete_server("5503857");
  //exit();
    
  


?>
