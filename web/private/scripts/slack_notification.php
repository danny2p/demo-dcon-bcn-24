<?php

// Important constants :)
$pantheon_yellow = '#EFD01B';

// Default values for parameters - this will assume the channel you define the webhook for.
// The full Slack Message API allows you to specify other channels and enhance the messagge further
// if you like: https://api.slack.com/docs/messages/builder
$defaults = array(
  'slack_username' => 'Pantheon-Quicksilver',
  'always_show_text' => false,
);

// Load our hidden credentials.
// uses Pantheon secret manager plugin: 
// https://github.com/pantheon-systems/terminus-secrets-manager-plugin
$secrets = array (
  'slack_url' => pantheon_get_secret('slack_url'),
  'slack_status' => pantheon_get_secret('slack_status'),
);


// Build an array of fields to be rendered with Slack Attachments as a table
// attachment-style formatting:
// https://api.slack.com/docs/attachments
$fields = array(
  array( // Render Environment name with link to site, <http://{ENV}-{SITENAME}.pantheon.io|{ENV}>
    'title' => 'Site',
    'value' => $_ENV['PANTHEON_SITE_NAME'] . ' (' . $_ENV['PANTHEON_ENVIRONMENT'] . ') - ' . 'http://' . $_ENV['PANTHEON_ENVIRONMENT'] . '-' . $_ENV['PANTHEON_SITE_NAME'] . '.pantheonsite.io',
    'short' => 'true'
  ),
  array( // Render Name with link to Email from Commit message
    'title' => 'Deployed By',
    'value' => $_POST['user_email'],
    'short' => 'true'
  ),
  array( // Render workflow phase that the message was sent
    'title' => 'Triggering Workflow',
    'value' => ucfirst($_POST['stage']) . ' ' . str_replace('_', ' ',  $_POST['wf_type']),
    'short' => 'true'
  )
);

$workflow_info = '';

foreach ($fields as $field) {
  $workflow_info .= $field['title'] . ": ";
  $workflow_info .= $field['value'];
  $workflow_info .= "\n";
}

// Customize the message based on the workflow type.  Note that slack_notification.php
// must appear in your pantheon.yml for each workflow type you wish to send notifications on.
print "Workflow Type: " . $_POST['wf_type'] . "\n";

switch($_POST['wf_type']) {
  case 'deploy':
    // Find out what tag we are on and get the annotation.
    $deploy_tag = `git describe --tags`;
    $deploy_message = $_POST['deploy_message'];

    // Prepare the slack payload as per:
    // https://api.slack.com/incoming-webhooks
    $text = "------------- :lightningbolt-vfx: " . ucwords($_ENV['PANTHEON_ENVIRONMENT']) . " Deployment :lightningbolt-vfx: ------------- \n";
    if ($_ENV['PANTHEON_ENVIRONMENT'] == "test") { 
      $text .= "\nHey QA Team - Please Review! \n\n";
    } 

    $text .= $workflow_info;
    $text .= "Deploy Log: $deploy_message \n";
    break;

  case 'sync_code':
  case 'sync_code_with_build':
  case 'merge_cloud_development_environment_into_dev':
    // Get the committer, hash, and message for the most recent commit.
    $committer = `git log -1 --pretty=%cn`;
    $email = `git log -1 --pretty=%ce`;
    $message = `git log -1 --pretty=%B`;
    $hash = `git log -1 --pretty=%h`;
    // Prepare the slack payload as per:
    // https://api.slack.com/incoming-webhooks
    $text = "------------- :building_construction: Commit to Dev :building_construction: ------------- \n";
    if ($_ENV['PANTHEON_ENVIRONMENT'] == "dev") { 
      $text .= "\n:eyes: Hey senior devs Please Review!  \n";
    } elseif (strpos($_ENV['PANTHEON_ENVIRONMENT'], 'd-') === 0 || $_ENV['PANTHEON_ENVIRONMENT'] == 'qs') { //indicating a branch with design / theme work
      $text = "------------- :building_construction: Commit to Design Branch :building_construction: ------------- \n";
      $text .= "\n:eyes: Hey design team Please review new theme work! \n";
    } else {
      $text = "------------- :building_construction: Commit to " . $_ENV['PANTHEON_ENVIRONMENT'] . " Multidev :building_construction: ------------- \n";
    }

    $text .= $workflow_info;
    $text .= "Commit Log: " . rtrim($message);
    
    print $text;

    
    /*
    $text .= 'Code sync to the ' . $_ENV['PANTHEON_ENVIRONMENT'] . ' environment of ' . $_ENV['PANTHEON_SITE_NAME'] . ' by ' . $_POST['user_email'] . "!\n";
    $text .= 'Most recent commit: ' . rtrim($hash) . ' by ' . rtrim($committer) . ': ' . $message;
    $text .= rtrim($message);
    */
    break;

  case 'clear_cache':
    $text = "Cache Cleared";
    break;

  default:
    $text = $_POST['qs_description'];
    break;
}

if ($secrets['slack_status'] != "disabled") {
  _slack_notification($secrets['slack_url'],$text);
}



/**
 * Send a notification to slack
 */
function _slack_notification($slack_url,$text)
{
  $payload = json_encode([
    'text' => $text
  ]);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $slack_url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  // Watch for messages with `terminus workflows watch --site=SITENAME`
  print("\n==== Posting to Slack ====\n");
  $result = curl_exec($ch);
  print("RESULT: $result");
  // $payload_pretty = json_encode($post,JSON_PRETTY_PRINT); // Uncomment to debug JSON
  // print("JSON: $payload_pretty"); // Uncomment to Debug JSON
  print("\n===== Post Complete! =====\n");
  curl_close($ch);
}