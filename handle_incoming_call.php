<?php
    require_once('./include/config.php');
    header('Content-type: text/xml');

    // DB object construtor found in include/PhoneTrackingDatabase.php
    $db_object = new PhoneTrackingDatabase();

    // twilio request params are returned into variable $request_params_from_twilio
    $request_params_from_twilio = $db_object->get_number_info();

    // twilio tracking number is transfered from $request_params_from_twilio array to $twilio_tracking_number variable
    $twilio_tracking_number = $request_params_from_twilio[3];
    $twilio_from_number = $request_params_from_twilio[2];

    // assigns the database row of values associated with the called twilio number to $number_array
    $number_array = $db_object->select_from_node_contact($twilio_tracking_number);

    // direct number of sales agent is extracted from array
    $agent_number = $number_array['direct_number'];


    // if ($db_object->twilio_authentication($request_params_from_twilio, $AuthToken) == true) {

      if ($db_object->caller_has_called_before($twilio_from_number) === false) {
        // concatenates the array of request params and the array of values from node_contact and inserts into calls db
        if (is_array($number_array) && is_array($request_params_from_twilio)) {
          $db_object->write_to_db($number_array, $request_params_from_twilio);
        }
        else {
          if (!is_array($number_array)) {
            error_log(__FILE__ . ': ' . __LINE__ . ': Bad data from database: ' .
            var_export($number_array, TRUE));
          }
          if (!is_array($request_params_from_twilio)) {
            error_log(__FILE__ . ': ' . __LINE__ . ': Bad data from Twilio: ' .
            var_export($request_params_from_twilio, TRUE));        }
        }
      }
      else {
        error_log(__FILE__ . ': ' . __LINE__ . ': Duplicate number: ' .
                  $twilio_from_number);
      }
    // }
?>

<!-- Twilio XML "verb" that executes a call to the number in $agent_number variable, and sends data from call to the callback function specified in "Call Tracking For Google Analytics" Twiml app (Callback settings can be changed for numbers in bulk here: https://www.twilio.com/user/account/apps ) -->
<Response>
    <Dial method="POST" record="false"><?php echo $agent_number; ?></Dial>
</Response>

