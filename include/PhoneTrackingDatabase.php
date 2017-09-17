<?php

Class PhoneTrackingDatabase {
  protected $db;

  function __construct() {
    $this->db = new PDO('mysql:host=yourhost;dbname=your_db',
                        'phone_tracking',
                        'yourpassword');
  }

  // method gets twilio request parameters and returns them in an array
  function get_number_info() {

    $CallSid = $_POST['CallSid'];
    $AccountSid=$_POST['AccountSid'];
    $CallFrom=$_POST['From'];
    $CallTo=$_POST['To'];
    $CallStatus=$_POST['CallStatus'];
    $ApiVersion=$_POST['ApiVersion'];
    $Direction=$_POST['Direction'];

    if (isset($_POST['FromCity'])) {
      $FromCity=$_POST['FromCity'];
      $FromState=$_POST['FromState'];
      $FromZip=$_POST['FromZip'];
      $FromCountry=$_POST['FromCountry'];
    } else {
      $FromCity="";
      $FromState="";
      $FromZip="";
      $FromCountry="";
    }
    $ToCity=$_POST['ToCity'];
    $ToState=$_POST['ToState'];
    $ToZip=$_POST['ToZip'];
    $ToCountry=$_POST['ToCountry'];

    $vars = array($CallSid,$AccountSid,$CallFrom,$CallTo,$CallStatus,$ApiVersion,$Direction,$FromCity,$FromState,$FromZip,$FromCountry,$ToCity,$ToState,$ToZip,$ToCountry);
    return $vars;
  }

  // method takes as input twilio tracking number, return as output row array corresponding to that number in node_contact database
  function select_from_node_contact($twilio_tracking_number) {
    $row = NULL;
    $query = "SELECT nid, tid, direct_number FROM node_contact WHERE tracking_number = ?";
    $stmt = $this->db->prepare($query);
    if ($stmt !== FALSE) {
      $result = $stmt->bindParam(1, $twilio_tracking_number);
      if ($result !== FALSE) {
        $result = $stmt->execute();
        if ($result !== FALSE) {
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($row === FALSE) {
            $errInfo = $stmt->errorInfo();
            error_log(__FILE__ . ': ' . __LINE__ . ': ' . __METHOD__ . ': ' .
                      var_export($errInfo, TRUE));
          }
        }
        else {
          $errInfo = $stmt->errorInfo();
          error_log(__FILE__ . ': ' . __LINE__ . ': ' . __METHOD__ . ': ' .
                    var_export($errInfo, TRUE));
        }
      }
      else {
        $errInfo = $stmt->errorInfo();
        error_log(__FILE__ . ': ' . __LINE__ . ': ' . __METHOD__ . ': ' .
                  var_export($errInfo, TRUE));
      }
    }
    else {
      $errInfo = $this->db->errorInfo();
      error_log(__FILE__ . ': ' . __LINE__ . ': ' . __METHOD__ . ': ' .
                var_export($errInfo, TRUE));
    }

    if (empty($row)) {
      error_log(__FILE__ . ': ' . __LINE__ . ': ' . __METHOD__ .
          ': Could not retrieve contact for twilio_tracking_number=' .
          var_export($twilio_tracking_number, TRUE));
    }
    return $row;
  }

  // method takes in vasriables from get_number_info() and select_from_node_contact(), concatenates the arrays, and sends them to db.
  function write_to_db($returned_from_read, $request_parameters) {

    $calls_insert_array = $request_parameters + $returned_from_read;
    try {
        $db_write = $this->db->prepare('INSERT INTO calls (DateCreated,CallSid,AccountSid,CallFrom,CallTo,CallStatus,ApiVersion,Direction,FromCity,FromState,FromZip,FromCountry,ToCity,ToState,ToZip,ToCountry,Nid,Tid,DirectNumber) VALUES (CURRENT_TIMESTAMP,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $db_write->execute(array_values($calls_insert_array));
    }

    catch (Exception $e) {
      error_log(__FILE__ . ': ' . __LINE__ . ': The insert in handle_incoming didnt work');
      exit;
    }
  }

  /**
   * Update call record with call duration
   */
  function update_call_log() {
    $CallSid = filter_input(INPUT_POST, 'CallSid', FILTER_VALIDATE_REGEXP,
                              array(
                                'options' => array(
                                  'regexp' => '/^[A-Za-z0-9]{4,}$/',
                                ),
                              )
                            );
    $CallDuration = filter_input(INPUT_POST,
                                  'CallDuration',
                                  FILTER_VALIDATE_REGEXP,
                                  array(
                                    'options' => array(
                                      'regexp' => '/^[0-9]{1,5}$/',
                                    ),
                                  )
                                );
    if (!empty($CallSid) && !empty($CallDuration)) {
      $sql = 'UPDATE
                calls
              SET
                CallDuration = :CallDuration
              WHERE
                CallSid = :CallSid';
      try {
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':CallSid', $CallSid, PDO::PARAM_STR);
        $stmt->bindParam(':CallDuration', $CallDuration, PDO::PARAM_INT);
        $stmt->execute();
      }
      catch (Exception $e) {
        error_log(__FILE__ . ': ' . __METHOD__ . ': ' . __LINE__ .
          $e->getMessage());
      }
    }
    else {
      if (empty($CallSid)) {
        $err_descr_str = 'CallSid=' . $_POST['CallSid'];
      }
      else {
        $err_descr_str = 'CallDuration=' . $_POST['CallDuration'];
      }
      error_log(__FILE__ . ': ' . __METHOD__ . ': ' . __LINE__ .
                ': Invalid parameter: ' . $err_descr_str);
    }
  }

  /**
   * Retrieve data for a specific call record.
   *
   * @param string $call_sid  - alphanumeric Twilio CallSid
   * @return array            - array of name-value pairs from record, or FALSE
   *                            if not found
   */
  function get_call_record($call_sid) {
    if (preg_match('/^[A=Za-z0-9]{4,}$/', $call_sid) === 1) {
      $sql = 'SELECT
                *
              FROM
                calls
              WHERE
                CallSid = :CallSid';
      $stmt = $this->db->prepare($sql);
      if ($stmt) {
        $stmt->bindParam(':CallSid', $call_sid, PDO::PARAM_STR);
        if ($stmt->execute()) {
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($row !== FALSE) {
            // Success, return the array from the db row.
            return $row;
          }
          else {
            error_log(__FILE__ . ': ' . __METHOD__ . ': ' . __LINE__ .
                      ': Error fetching record');
          }
        }
        else {
          error_log(__FILE__ . ': ' . __METHOD__ . ': ' . __LINE__ .
                    ': Error executing SQL statement');
        }
      }
      else {
        error_log(__FILE__ . ': ' . __METHOD__ . ': ' . __LINE__ .
                  ': Error preparing SQL statement');
      }
    }
    else {
      error_log(__FILE__ . ': ' . __METHOD__ . ': ' . __LINE__ .
                ': Invalid $call_sid value');
    }

    // Some error occurred.
    return FALSE;
  }

  /**
   * Get the database object.
   *
   * @return object   - the PDO object.
   */
  function get_pdo_object() {
    return $this->db;
  }

  // Checks if a number has called before, used to make sure only unique sales calls are counted. Takes as input POST['From'] Number. Outputs a boolean either true or false
  function caller_has_called_before($from_number) {
    $row = FALSE;
    $from_query = 'SELECT
                    CallDuration
                   FROM
                    calls
                   WHERE
                    CallFrom = :from_number';
    try {
      $from_stmt = $this->db->prepare($from_query);
      $from_stmt->bindParam(':from_number', $from_number, PDO::PARAM_STR);
      $from_stmt->execute();
      $row = $from_stmt->fetch(PDO::FETCH_ASSOC);
    }
    catch (Exception $e) {
      error_log(__FILE__ . ': ' . __METHOD__ . ': ' . __LINE__ .
        $e->getMessage());
    }

    if (($row === FALSE) || empty($duration = $row['CallDuration'])) {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  function twilio_authentication($variables_from_get_number_info, $twil_auth_token) {

    // make sure the twilio-php library from config file is included somewhere in the file where this script runs. Twilio has its own working class for validation

    $validator = new Services_Twilio_RequestValidator($twil_auth_token);

    // The Twilio request URL.

    $twiml_app_url = 'https://www.yourwebsite.com/phone_tracking_google_analytics/handle_incoming_call.php?';

    // The X-Twilio-Signature header
    $signature = $_SERVER["HTTP_X_TWILIO_SIGNATURE"];

    if ($validator->validate($signature, $twiml_app_url, $variables_from_get_number_info)) {
        return true;
    }
    else {
        error_log(__FILE__ . ': ' . __LINE__ . ': Something is fishy. This request not sent from twilio');
        return false;
    }
  }

}
