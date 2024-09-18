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
  'slack_channel' => pantheon_get_secret('slack_channel'),
  'slack_username' => pantheon_get_secret('slack_username'),
  'always_show_text' => true
);
// Build an array of fields to be rendered with Slack Attachments as a table
// attachment-style formatting:
// https://api.slack.com/docs/attachments
$fields = array(
  array(
    'title' => 'Site',
    'value' => $_ENV['PANTHEON_SITE_NAME'],
    'short' => 'true'
  ),
  array( // Render Environment name with link to site, <http://{ENV}-{SITENAME}.pantheon.io|{ENV}>
    'title' => 'Environment',
    'value' => '<http://' . $_ENV['PANTHEON_ENVIRONMENT'] . '-' . $_ENV['PANTHEON_SITE_NAME'] . '.pantheonsite.io|' . $_ENV['PANTHEON_ENVIRONMENT'] . '>',
    'short' => 'true'
  ),
  array( // Render Name with link to Email from Commit message
    'title' => 'By',
    'value' => $_POST['user_email'],
    'short' => 'true'
  ),
  array( // Render workflow phase that the message was sent
    'title' => 'Workflow',
    'value' => ucfirst($_POST['stage']) . ' ' . str_replace('_', ' ',  $_POST['wf_type']),
    'short' => 'true'
  ),
  array(
    'title' => 'View Dashboard',
    'value' => '<https://dashboard.pantheon.io/sites/'. PANTHEON_SITE .'#'. PANTHEON_ENVIRONMENT .'/deploys|View Dashboard>',
    'short' => 'true'
  ),
);

// Customize the message based on the workflow type.  Note that slack_notification.php
// must appear in your pantheon.yml for each workflow type you wish to send notifications on.
switch($_POST['wf_type']) {
  case 'deploy':
    // Find out what tag we are on and get the annotation.
    $deploy_tag = `git describe --tags`;
    $deploy_message = $_POST['deploy_message'];

    // Prepare the slack payload as per:
    // https://api.slack.com/incoming-webhooks
    $text = '------------- Code Deployment ------------- \n';
    $text = 'Deploy to the '. $_ENV['PANTHEON_ENVIRONMENT'];
    $text .= ' environment of '. $_ENV['PANTHEON_SITE_NAME'] .' by '. $_POST['user_email'] .' complete!';
    $text .= ' <https://dashboard.pantheon.io/sites/'. PANTHEON_SITE .'#'. PANTHEON_ENVIRONMENT .'/deploys|View Dashboard>';
    if ($_ENV['PANTHEON_ENVIRONMENT'] == "test") { 
      $text .= '\n Hey QA Team - @test-qa-team  - Please Review!';
    } 

    // Build an array of fields to be rendered with Slack Attachments as a table
    // attachment-style formatting:
    // https://api.slack.com/docs/attachments
    $fields[] = array(
      'title' => 'Details',
      'value' => $text,
      'short' => 'false'
    );
    $fields[] = array(
      'title' => 'Deploy Note',
      'value' => $deploy_message,
      'short' => 'false'
    );  
    break;

  case 'sync_code':
    // Get the committer, hash, and message for the most recent commit.
    $committer = `git log -1 --pretty=%cn`;
    $email = `git log -1 --pretty=%ce`;
    $message = `git log -1 --pretty=%B`;
    $hash = `git log -1 --pretty=%h`;
https://slack.com/shortcuts/Ft078VS8HZ6X/e8785cc9320ea403461e8d26239c16ff
    // Prepare the slack payload as per:
    // https://api.slack.com/incoming-webhooks
    $text = '------------- Commit to Dev ------------- \n';
    $text .= 'Code sync to the ' . $_ENV['PANTHEON_ENVIRONMENT'] . ' environment of ' . $_ENV['PANTHEON_SITE_NAME'] . ' by ' . $_POST['user_email'] . "!\n";
    $text .= 'Most recent commit: ' . rtrim($hash) . ' by ' . rtrim($committer) . ': ' . $message;
    if (strpos($environment, 'd-') === 0) { //indicating a branch with design / theme work
      $text .= '\n Hey design team - @steve.bresnick - review neme theme work!';
    } else {
      $text .= '\n Hey senior devs - @danny.pfeiffer @katie.walters  - Please Review!';
    }


    // Build an array of fields to be rendered with Slack Attachments as a table
    // attachment-style formatting:
    // https://api.slack.com/docs/attachments
    $fields = array_merge($fields, array(
      array(
        'title' => 'Commit',
        'value' => rtrim($hash),
        'short' => 'true'
      ),
      array(
        'title' => 'Commit Message',
        'value' => rtrim($message),
        'short' => 'false'
      )
    ));
    break;

  case 'clear_cache':
    $fields[] = array(
      'title' => 'Cleared caches',
      'value' => 'Cleared caches on the ' . $_ENV['PANTHEON_ENVIRONMENT'] . ' environment of ' . $_ENV['PANTHEON_SITE_NAME'] . "!\n",
      'short' => 'false'
    );
    break;

  default:
    $text = $_POST['qs_description'];
    break;
}

$attachment = array(
  'fallback' => $text,
  'pretext' => ($_POST['wf_type'] == 'clear_cache') ? 'Caches cleared :construction:' : 'Deploying :rocket:',
  'color' => $pantheon_yellow, // Can either be one of 'good', 'warning', 'danger', or any hex color code
  'fields' => $fields
);

_slack_notification($secrets['slack_url'], $secrets['slack_channel'], $secrets['slack_username'], $text, $attachment, $secrets['always_show_text']);


/**
 * Send a notification to slack
 */
function _slack_notification($slack_url, $channel, $username, $text, $attachment, $alwaysShowText = false)
{
  $attachment['fallback'] = $text;
  $post = array(
    'username' => $username,
    'channel' => $channel,
    'icon_emoji' => ':lightning_cloud:',
    'attachments' => array($attachment)
  );
  if ($alwaysShowText) {
    $post['text'] = $text;
  }
  $payload = json_encode($post);
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