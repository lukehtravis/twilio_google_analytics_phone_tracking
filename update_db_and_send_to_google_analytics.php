<?php
  /**
   * @file
   * Phone tracking call completion handler for Twilio.
   *
   * POST parameters accepted:
   *  Twilio request params
   *  your_test      - if set, then run in test mode: send email to webmaster
   *                      and use Google Analytics mock script
   */

  require_once './include/config.php';
  require_once './include/youranalyticshandler.php';
  require_once './include/yourdrupalnodeandtaxonomyclass.php';

  // DB object constructor found in include/PhoneTrackingDatabase.php
  $db_object = new PhoneTrackingDatabase();

  // twilio request params are returned into variable $request_params_from_twilio
  $request_params_from_twilio = $db_object->get_number_info();

  $twilio_from_number = $request_params_from_twilio[2];

  if ($db_object->caller_has_called_before($twilio_from_number) === FALSE) {

    // Update the call record with the call duration.
    $db_object->update_call_log();

    // Also retrieve the call duration.
    $call_duration = filter_input(INPUT_POST,
                                  'your_call_duration',
                                  FILTER_VALIDATE_REGEXP,
                                  array(
                                    'options' => array(
                                      'regexp' => '/^[0-9]{1,5}$/',
                                    ),
                                  )
                                 );

    // twilio tracking number is transfered from $request_params_from_twilio array to $twilio_tracking_number variable
    $twilio_tracking_number = $request_params_from_twilio[3];

    // assigns the database row of values associated with the called twilio number to $number_array
    $number_array = $db_object->select_from_node_contact($twilio_tracking_number);

    // nid and tid are extracted from array
    $nid = $number_array['yournodeid'];
    $tid = $number_array['yourtaxonomyid'];

    // Call DrupalNode And Taxonomy Class
    $drupal_object = new DrupalNodeAndTaxonomy();

    // Check for test mode.
    $your_test_mode = filter_input(INPUT_POST,
                                  'your_test_mode',
                                  FILTER_VALIDATE_INT);

    // Put salesperson info into array and break into variables
    $salesperson_array = $drupal_object->get_salesperson_info($tid);
    $salesperson_full_name = $salesperson_array['full_name'];
    $salesperson_email_address = $salesperson_array['email_address'];

    if (!empty($your_test_mode)) {
      $salesperson_email_address = 'webmaster@yourorg.com';
    }

    // Put node path into a variable to be used to send to GA
    $path_of_node = $drupal_object->get_drupal_node_path($nid);

    // Create values to be sent to google analytics
    $event_category = "Goal Actions"; // Goes with ec url param
    $event_action = "Call"; // Goes with ea url param. There should be a conversation about how we want to track these calls in analytics
    $event_label = $salesperson_full_name; // Name of the person who was called.
    $event_value = intval($call_duration); //Goes with ev url param. This allows us to specify a value for the actual event when we decide on one. Must be an integer

    // Load class for Google Analytics.
    $ga = new GoogleAnalytics();

    //if ($db_object->caller_has_called_before($twilio_from_number) == false && $db_object->twilio_authentication($request_params_from_twilio, $AuthToken) == true ) {
      // Send event tracking request to GA.
      $ga->generate_analytics_event('phone',
                                    'www.yourorg.com',
                                    '/' . $path_of_node,
                                    $event_category,
                                    $event_action,
                                    $event_label,
                                    $event_value,
                                    $test_mode);
    //}

    // Send an email notification to the salesperson.
    $subject = 'Your Org Website Tracked Call Receipt';
    $message = <<<___MAIL
The call you just received was from a tracked number listed on the yourorg website.

Page:
http://www.yourorg.com/{$path_of_node}

The number of the caller was {$twilio_from_number}
___MAIL;

    $timenow = new DateTime();
    $timenow->setTimezone(new DateTimeZone('America/Los_Angeles'));
    $message = wordwrap($message, 72, "\n");
    $reply_to = 'webmaster@yourorg.com';
    $headers = 'Date: ' . $timenow->format('d M Y H:i:s O') . "\r\n"
             . 'From: donotreply@yourorg.com' . "\r\n"
             . 'Reply-To: ' . $reply_to . "\r\n"
             . 'Content-type: text/plain; charset=UTF-8' . "\r\n"
             . 'X-Mailer: PHP/' . phpversion();

    error_log(__FILE__ . ': ' . __LINE__ . ': Sending email to ' .
        $salesperson_email_address . ': ' . $message);
    mail($salesperson_email_address, $subject, $message, $headers);
  }
  else {
    error_log(__FILE__ . ': ' . __LINE__ . ': Duplicate number: ' .
              $twilio_from_number);
  }
  // Send an empty response to Twilio.
  header('Content-type: text/xml');

?>
<Response/>
