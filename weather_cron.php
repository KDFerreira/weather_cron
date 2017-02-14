
<?php

require 'config/config.php';
require 'app_tokens_gwTech.php';
// Create an OAuth connection
require 'tmhOAuth.php';

$connection = new tmhOAuth(array(
  'consumer_key'    => $consumer_key,
  'consumer_secret' => $consumer_secret,
  'user_token'      => $user_token,
  'user_secret'     => $user_secret
));

$search_terms = array('%40grewpworkTech');

$db = mysqli_connect($dbConfig['host'],$dbConfig['user'],$dbConfig['pass'],$dbConfig['database']);
if (!$db) {
    die('Could not connect: ' . mysql_error());
}

set_time_limit(60*5);
for ($i = 0; $i < 9; $i++) {
//$t = microtime(true);
    
////////////////////////////////// **************************   
// code in here


//search for grewpworkTech mentions
foreach ($search_terms as $search) {

//query the newest tweet we have
$result = $db->query("SELECT tweet_id FROM tweet_mentions WHERE target_screen_name = '$search' ORDER BY tweet_id DESC LIMIT 1");
//echo "SELECT tweet_id FROM tweet_mentions WHERE target_screen_name = '$search' ORDER BY tweet_id DESC LIMIT 1"."\n";
$row = mysqli_fetch_assoc($result);
$since_id = $row['tweet_id'];

if(isset($since_id)) {
    

//query the oldest tweet we have
//$result = $db->query('SELECT tweet_id FROM tweet_mentions ORDER BY tweet_id LIMIT 1');
//$row = mysqli_fetch_assoc($result);
//$max_id = $row['tweet_id']-1;

// Request the most recent 100 matching tweets
$http_code = $connection->request('GET',$connection->url('1.1/search/tweets'), 
		array(
			'q' => $search,
			'since_id' => $since_id,
			'count' => 5
		));

// Search was successful
if ($http_code == 200) {
		
	// Extract the tweets from the API response
	$response = json_decode($connection->response['response'],true);
	$tweet_data = $response['statuses'];
	
	foreach($tweet_data as $tweet) {
            
            // Ignore any retweets
            if (!isset($tweet['retweeted_status'])) {
		
			
		
		$tweet_id = $tweet['id'];
		$screen_name = mysqli_real_escape_string($db,$tweet['user']['screen_name']);
		$name = mysqli_real_escape_string($db,$tweet['user']['name']);		
		$profile_image_url = mysqli_real_escape_string($db,$tweet['user']['profile_image_url']);
		$tweet_id = mysqli_real_escape_string($db,$tweet['id_str']);
		$tweet_text = mysqli_real_escape_string($db,$tweet['text']);
		$created_at = mysqli_real_escape_string($db,$tweet['created_at']);
		$created_at = strtotime($created_at);
		$date_collected = time();
		
		$target_screen_name = $search;
                
                // process the weather request
                $temp = explode("@grewpworkTech ",$tweet_text);
                $city = $temp[1];
         
                if(isset($city))
                {
                    $url = 'http://api.openweathermap.org/data/2.5/weather?q='.urlencode($city).'&units=metric';
                    
                    $curl = curl_init();
                    //echo 'hello<br/>';
                    $headers = array();
                    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($curl, CURLOPT_HEADER, 0);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_URL, $url);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
                    //echo 'hello2<br/>';
                    $json = curl_exec($curl);
                    //echo "json: ".$json."<br/>";
                    curl_close($curl);
                    //echo "json2: ".$json."<br/>";
                    
                    $data = json_decode($json, TRUE);
                    if($screen_name != 'grewpworkTech') {
                        //print_r($data);
                        if(!empty($data['name'])) {
                            $gwTweet = '@'.$screen_name.' Current weather in '.$data['name'].' is: '.$data['weather'][0]['main'].', and the temperature is: '.$data['main']['temp'].' degrees Celsius. #grewpwork #weatherAPP';
                            // Send a tweet
                            $code = $connection->request('POST', 
                                    $connection->url('1.1/statuses/update'), 
                                    array('status' => $gwTweet));
                            
                            //input the city data into DB
                            
                            /////////////////////////
                            
                            //input city tweet
                            $t = time();
                            $query = "
                                    INSERT into city_tweets
                                    set     city = '".$city."',
                                            date = ".$t.",
                                            screen_name = '".$screen_name."',
                                            temp = ".$data['main']['temp'];
                                    
                            $db->query($query); 
                            
                            //check if city record exists
                            $query = "
                                    SELECT num
                                    FROM cities
                                    where city = '".$city."'
                            ";
                            $result = $db->query($query); 
                            if($result->num_rows == 0) {
                                    $num = 1;
                                    //insert city
                                    $query = "
                                    INSERT into cities
                                    set     city = '".$city."',
                                            lat = ".$data['coord']['lat'].",
                                            lng = ".$data['coord']['lon'].",
                                            num = ".$num;
                                    
                                    $db->query($query);
                            }
                            else {
                                    //get the previous value for num
                                    $row = mysqli_fetch_assoc($result);
                                    $num = $row['num'] + 1;
                                    $query = "
                                                UPDATE cities
                                                SET num = ".$num."
                                                WHERE city = '".$city."'";
            
                                    $db->query($query); 
                                    
                                    
                            }
                            
                            /////////////////////////
                            
                        }
                        else {
                            $gwTweet = '@'.$screen_name.' Please try searching with a valid city name we could not process "'.$city.'". #grewpwork #weatherAPP';
                            // Send a tweet
                            $code = $connection->request('POST', 
                                    $connection->url('1.1/statuses/update'), 
                                    array('status' => $gwTweet));
                        }
                    } //end if screen_name
                    
        //            $to = 'k.ferreira.mie@gmail.com';
        //	    $subj = 'send code';
        //	    $body = 'code: '.$code;
        //	    mail($to,$subj,$body);
                    
                    
                } // end if city
		
		//input tweet
		$query = "
			INSERT into tweet_mentions
			set 	tweet_id = '".$tweet_id."',
				created_at = ".$created_at.",
				source_screen_name = '".$screen_name."',
				target_screen_name = '".$target_screen_name."',
				tweet_text = '".$tweet_text."'";
			
		$db->query($query); 
		
		//check if user exists
		$query = "
			SELECT name
			FROM screen_names
			where screen_name = '".$screen_name."'
		";
		$result = $db->query($query); 
		if($result->num_rows == 0) {
			//insert user
			$query = "
			INSERT into screen_names
			set 	screen_name = '".$screen_name."',
				name = '".$name."',
				profile_image_url = '".$profile_image_url."',
				date_collected = ".$date_collected;
			
			$db->query($query);
		}
	
                
            }// end if reTweet
        }//end of for each tweet loop
// Handle errors from API request
} // end if http_code == 200

else {
	if ($http_code == 429) {
	    $to = 'k.ferreira.mie@gmail.com';
	    $subj = 'code error 429';
	    $body = 'Error: Twitter API rate limit reached';
	    mail($to,$subj,$body);
	}
	//else {
	//    $to = 'k.ferreira.mie@gmail.com';
	//    $subj = 'code error: '.$http_code;
	//    $body = 'Error: Twitter was not able to process';
	//    mail($to,$subj,$body);
	//}
    
} // end else http code == 200

}// end of if since id is set
}//end of foreach search term	

    
    
////////////////////////////////// **************************

sleep(30);

} // end of time loop


$db->close();


?>