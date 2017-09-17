<?php

  // There are lots of parameters that can be used to send data to google analytics. The parameter list is found here
  // https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters

  /*
  1. Protocol Version | Type - String | Var name - v | Required
    -ex. v=1 (will be 1 for us)

  2. Tracking ID/Web Property ID | Type - string | Var name - tid | Required
    -ex. tid=UA-XXX-XX (input our web property id into string area)

  3. Client ID | Type - String | Var name - cid | Required

  4. Hit Type | Type - String | var name - t | Required
    -ex. t=event

  5. Event Category | Type - String | var name - ec
    -ex. ec=Goal Actions

  6. Event Action | Type - Any String | var name - ea
    -ex. ea=Call

  7. Event Label | Any String | var name - el
    -ex. el=Name Of Person Called

  8. Event Value | Non negative integer | var name - ev
    -ex. ev=55
  */


/**
 * Class for interacting with Google Analytics from PHP.
 */
class GoogleAnalytics
{
  // Google Analytics Tracking ID
  private $gatid = 'your google tracking id';


  /**
   * Generate a GoogleAnalytics event.
   *
   * @param string $data_source   - ds. Indicates the data source of the hit, e.g. 'web', 'app', 'phone'.
   * @param string $document_host - dh. Specifies the hostname from which content was hosted. Max 100 bytes.
   * @param string $document_path - dp. The path portion of the page URL. Should begin with '/'. Max 2048 bytes.
   * @param string $event_category  ec. Specifies the event category. Max length 150 bytes.
   * @param string $event_action  - ea. Specifies the event action. Max length 500 bytes.
   * @param string $event_label   - el. Specifies the event label. Max length 500 bytes.
   * @param integer $event_value  - ev. Specifies the event value. Values must be non-negative.
   * @param boolean $test_mode    - (optional) if TRUE, send request to ga_mock.php instead of GA.
   */
  public function generate_analytics_event($data_source,
                                           $document_host,
                                           $document_path,
                                           $event_category,
                                           $event_action,
                                           $event_label,
                                           $event_value,
                                           $test_mode=FALSE) {

    // Create a unique Client ID (required for all measurement protocol hits)
    $uuid = $this->create_uuidv4();

    // Map the GA parameters to their values.
    $ga_params = array(
      'v' => '1',
      'tid' => $this->gatid,
      'ds' => $data_source,
      'cid' => $uuid,
      't' => 'event',
      'dh' => $document_host,
      'dp' => $document_path,
      'ec' => $event_category,
      'ea' => $event_action,
      'el' => $event_label,
      'ev' => $event_value,
    );


    $ga_url = 'http://www.google-analytics.com/collect';

    // Send the data to Google Analytics.
    $ch = curl_init($ga_url);
    curl_setopt(CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt(CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($ga_params));
    $response = curl_exec($ch);
    if ($response === FALSE) {
      error_log(__FILE__ . ': ' . __LINE__ .
        ': Error sending data to Google Analytics');
    }
    curl_close($ch);
  }


  /**
   * Return a random UUID (version 4).
   *
   * @return string - a 128-bit UUID (version 4).
   */
  protected function create_uuidv4() {
    return $this->guidv4(openssl_random_pseudo_bytes(16));
  }


  /**
   * Generate a UUID (version 4) from a random number.
   * From http://stackoverflow.com/a/15875555
   *
   * @param string $data - a 128-bit (16-char) string of random bytes.
   * @return string - a 128-bit UUID (version 4).
   */
  protected function guidv4($data)
  {
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }
}
