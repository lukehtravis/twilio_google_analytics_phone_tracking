<?php
  // Configure PHP.
  ini_set('error_reporting', E_ALL);
  ini_set('date.timezone', 'yourtimezone');

  require "twilio.php"; // must be included in your instance
  require_once 'databasename';
  require_once 'TwilioInfo.php'; // also must be included in your instance
  $ApiVersion = "2010-04-01";
  $AccountSid = "youraccountsid";
  $AuthToken = "yourauthtoken";

  function format_phone($phone)
  {
    $phone = preg_replace("/[^0-9]/", "", $phone);

    if(strlen($phone) == 7)
      return preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $phone);
    elseif(strlen($phone) == 10)
      return preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "($1) $2-$3", $phone);
    else
      return $phone;
  }

  class Util {
    public static function get_all_twilio_numbers() {
      global $ApiVersion, $AccountSid, $AuthToken;
      $twilio_numbers=array();
        $client = new TwilioRestClient($AccountSid, $AuthToken);
      $response = $client->request("/$ApiVersion/Accounts/$AccountSid/IncomingPhoneNumbers", "GET"); // Get all twilio phone numbers
      foreach($response->ResponseXml->IncomingPhoneNumbers->IncomingPhoneNumber AS $number){
        $twilio_numbers[format_phone($number->PhoneNumber)]=$number->FriendlyName;
      }
      return $twilio_numbers;
    }
  }
