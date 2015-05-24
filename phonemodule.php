<?php

// View an explanation of this module at bit.ly/glucoseproject

function phonemodule_menu() {
    $items = array();
    $items['phonemodule'] = array(
      'page callback' => 'phonemodule_create_data',
      'access callback' => TRUE,
      'type' => MENU_CALLBACK,
    );
    $items['restricted'] = array(
      'page callback' => 'phonemodule_403',
      'access callback' => TRUE,
      'type' => MENU_CALLBACK,
    );
    return $items;
}

function phonemodule_403() {
  return t("Please log in or register to access your glucose chart.");
}

//Adds validation to phone number field on user registration/profile forms
//Makes end date functionality more intuitive for exposed date filter
function phonemodule_form_alter(&$form, &$form_state, $form_id) {
  if($form_id == 'user_profile_form' || $form_id == 'user_register_form') {
    $form['field_phone_number']['und'][0]['value']['#element_validate'] = array('phonemodule_validate');
  }
}

function phonemodule_validate($element, &$form_state, $form_id) {
  //Ensures exactly ten numeric digits are entered
  if (!(ctype_digit($element['#value']) && strlen($element['#value']) == 10)) {
    form_set_error('field_phone_number', t('Please enter a standard ten-digit phone number.'));
  }
  //Prevents multiple users from having the same phone number
  else {
    $uid = db_query("select entity_id from field_data_field_phone_number where field_phone_number_value = :phone",array(':phone' => $element['#value']))->fetchAssoc();
    $uid = $uid['entity_id'];
    if (isset($uid) && (($form_id['form_id']['#value'] == 'user_register_form') || ($uid != $form_state['user']->uid))) {
      form_set_error('field_phone_number', t('An account already exists with that phone number.'));
    }
  }
}

function phonemodule_create_data() {
  if (isset($_GET['msisdn']) && isset($_GET['text']) && isset($_GET['to']) && ($_GET['to'] == "12053796987")) {
    
    $number = $_GET['msisdn'];
    $number = substr($number, 1); //Removes country prefix
    $value = $_GET['text'];
    $uid = db_query("select entity_id from field_data_field_phone_number where field_phone_number_value = :phone",array(':phone' => $number))->fetchAssoc();

    if (!is_numeric($value)) {
      file_get_contents("https://rest.nexmo.com/sms/json?api_key=KEY&api_secret=SECRET&from=12053796987&to=1" . $number . "&text=Sorry,%20that%20is%20not%20a%20valid%20message.");
      drupal_exit();
    }

    if (isset($uid)) {
      $node = new stdclass();
      $node->type = 'glucose_level';
      $node->status = 1;
      $node->uid = $uid['entity_id'];
      $account = user_load($node->uid);
      $node->title = "Level for " . $account->name . ' on ' . date(DATE_RFC2822);
      $node->field_glucose_level['und'][0]['value'] = $value;
      node_save($node);
    }

    if ($value < 70) {
      file_get_contents("https://rest.nexmo.com/sms/json?api_key=KEY&api_secret=SECRET&from=12053796987&to=1" . $number . "&text=Warning!%20Your%20blood%20sugar%20seems%20low.%20Go%20acquire%20sugar.");
    }
    if ($value > 450) {
      file_get_contents("https://rest.nexmo.com/sms/json?api_key=KEY&api_secret=SECRET&from=12053796987&to=1" . $number . "&text=Warning!%20Your%20blood%20sugar%20seems%20very%20high.%20Take%20insulin%20or%20something.");
    }
  }
  return "";
}