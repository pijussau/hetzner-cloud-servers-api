<?php
  
  // This is for generating a random server name
  function generateRandom($min = 1, $max = 20) {
    if (function_exists('random_int')):
        return random_int($min, $max); // more secure
    elseif (function_exists('mt_rand')):
        return mt_rand($min, $max); // faster
    endif;
    return rand($min, $max); // old
  }
  
  // This is for replacing a server in case it stops working
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
  
  // In case you want to use a snapshot this command will get the latest snapshot
  $snapshotID = get_snapshots();
  
  $servers = 10;

  // This will generate 10 servers
  for($i = 1; $i <= $servers; $i++) {
    $serverName = strtoupper(generateRandomString(8));
    createServer($serverName, $snapshotID, $domain);
    sleep(generateRandom(1,5));
  }

  // This will delete a server
  delete_servers($serverID);
  
  function get_snapshots(){

    $crl = curl_init('https://api.hetzner.cloud/v1/images');
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($crl, CURLINFO_HEADER_OUT, true);

    curl_setopt($crl, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer YOUR_TOKEN',
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

  function delete_servers($serverID = ""){

    if (strlen($serverID) > 1){
      $crl = curl_init('https://api.hetzner.cloud/v1/servers/'.$serverID);
      curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($crl, CURLINFO_HEADER_OUT, true);
      curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "DELETE");

      curl_setopt($crl, CURLOPT_HTTPHEADER, array(
          'Authorization: Bearer YOUR_TOKEN',
      ));
    
      $result = curl_exec($crl);
    }
  }


  function createServer($serverName, $snapshotID, $domain){
    
    $serverTypes = array('nbg1', 'fsn1', 'hel1');
    $randIndex = array_rand($serverTypes);
    $serverLocation = $serverTypes[$randIndex];

    $post_data = '{"name": "'.$serverName.'","server_type": "cx11","location": "'.$serverLocation.'","start_after_create": true,"image": "'.$snapshotID.'"}';
 
    $crl = curl_init('https://api.hetzner.cloud/v1/servers');
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($crl, CURLINFO_HEADER_OUT, true);
    curl_setopt($crl, CURLOPT_POST, true);
    curl_setopt($crl, CURLOPT_POSTFIELDS, $post_data);

    curl_setopt($crl, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Authorization: Bearer YOUR_TOKEN',
    ));
    
    $result = curl_exec($crl);
   
    $ServerID = get_string_between($result, '"id": ', ',');
    $rootPassword = get_string_between($result, '"root_password": "', '"');
    echo $result;
    curl_close($crl);

  }


?>
