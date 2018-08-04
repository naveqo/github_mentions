<?php

if($_SERVER["REQUEST_METHOD"] == "POST") {

  $data = json_decode($_POST['payload'],true);

  //api token
  require_once('Config.php');

  //user list ( $ids )
  require_once('users.php');

  //comment status
  $action = $data["action"];

  //Switch chat room and sending type according to comment status
  $room_id = 0;
  $send_type = "";
  switch ($action) {
    case "created":
      $room_id = $cw_mention_room_id;
      $send_type = "tasks";
      break;
    case "edited":
      $room_id = $cw_commentlog_room_id;
      $send_type = "messages";
      break;
    case "deleted":
      $room_id = $cw_commentlog_room_id;
      $send_type = "messages";
      break;
    default:
      $room_id = $cw_mention_room_id;
      $send_type = "tasks";
  }

  //comment
  $data_body = $data["comment"]["body"];

  //sender
  $sender = "[piconname:" . $ids[$data["sender"]["login"]][0] . "]";

  //commented url
  $comment_url = $data["comment"]["html_url"];

  //issues pull request title
  $issue_title = $data["issue"]["title"] ? $data["issue"]["title"]: $data["pull_request"]["title"];

  //To
  $ids_n_names = array();

  //Task
  $to_ids = "";

  //sending flag
  $send_flag = true;

  //Pick out user name from $data_body
  //Exclude api authority user name
  $pattern = '/@(?!.*authority_user_name)([-_0-9a-zA-Z]*)/';
  preg_match_all($pattern, $data_body, $matches);
  $users = $matches[1];

  //Match user list with $ data_body
  foreach ($ids as $key => $id) {
    if (in_array($key, $users)) {
      $to_ids .= $id[0] . ",";
      $ids_n_names[] = "[To:" . $id[0] . "]" . $id[1];
    }
  }

  //Pick out task deadline for chat from $data_body
  $task_limitdate_pattern = '/:date:(\d{4}(?:-|\/)\d{1,2}(?:-|\/)\d{1,2})/';
  preg_match($task_limitdate_pattern, $data_body, $limit_matche);
  $task_limit = strtotime($limit_matche[1] . " 23:59:59");

/*
 * contents of send to chat 
*/
$send_body = "";

// create
if ($action == "created" && !empty($to_ids)) {
$send_body = <<<EOD
{$sender} commented on GitHub.\n\n
EOD;
foreach ($ids_n_names as $cw_user) {
  $send_body .= $cw_user . "\n";
}
$send_body .= <<<EOD
There is a message or related content. 
Please confirm.
[info]{$issue_title}
{$comment_url}[/info]
EOD;

// edit
} elseif ($action == "edited" && !empty($to_ids)) {

$send_body = <<<EOD
{$sender} edited the comment on GitHub.\n
[info]{$issue_title}
{$comment_url}
{$data_body}[/info]
EOD;

// delete
} elseif ($action == "deleted" && !empty($to_ids)) {

$send_body = <<<EOD
{$sender} deleted the comment on GitHub.\n
[info]{$issue_title}
{$comment_url}
{$data_body}[/info]
EOD;
} else {

//Otherwise do not send
$send_flag = false;
}

  // post data
  $params = array(
    'body' => $send_body,
    'to_ids' => $to_ids,
    'limit' => $task_limit
  );

  // set cURL options
  $options = array(
    CURLOPT_URL => "https://api.chatwork.com/v2/rooms/" . $room_id . "/" . $send_type,
    CURLOPT_HTTPHEADER => array('X-ChatWorkToken: '. CHATWORK_API_TOKEN),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($params, '', '&'),
  );

  // execute cURL
  if ($send_flag) {
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    curl_close($ch);
  }

} else {
  echo 'Request method is different';
}
