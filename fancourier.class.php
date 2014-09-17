<?php

/**
 * FanCourier class. Get services names and shipping cost estimates.
 */
class FanCourier {

  // Url settings.
  private $api_url = 'http://www.selfawb.ro';
  private $request_type = array(
    // A pair of request type and the endpoint that has to be added to the URL.
    'services' => 'order.php',
    'estimation' => 'tarif.php',
  );
  // User login settings.
  private $username = NULL;
  private $user_pass = NULL;
  private $client_id = NULL;
  // Service list response, raw and processed.
  private $services_raw = array();
  private $services = array();
  // Errors.
  private $errors = array();

  /**
   * Constructor.
   * @param type $data
   *  Login data.
   * @return \FanCourier
   */
  function __construct($data) {
    // Validate that required params are set.
    foreach (array('username', 'user_pass', 'client_id') as $var) {
      if (empty($data[$var])) {
        $this->setError("Invalid {$var} in " . __CLASS__ . ', line ' . __LINE__, 'general', 'notice');
        return $this;
      }

      $this->$var = $data[$var];
    }
    // Calculate the service list.
    $this->getServices();
  }

  /**
   * Get the service list.
   * @param type $refresh
   * @return \FanCourier
   */
  public function getServices($refresh = FALSE) {
    if (!empty($this->errors)) {
      return $this;
    }

    if (!empty($this->services) && !$refresh) {
      return $this->services;
    }

    $request_type = 'services';
    $services = $this->request($request_type);

    if (!$services) {
      $this->setError('No services returned by request in ' . __CLASS__ . ', line ' . __LINE__, 'services', 'notice');
      return $this;
    }

    $this->services_raw = str_getcsv($services, "\n");
    foreach ($this->services_raw as $service_info) {
      $service_details = explode(',', $service_info);
      $this->services[$service_details[0]] = $service_details[0];
    }

    return $this->services;
  }

  /**
   * Helper method to get default values for shipping.
   * @return type
   */
  private function _getShippingDefaults() {
    return array(
      'plata_la' => 'destinatar',
      'plicuri' => '0',
      'colete' => '1',
      'greutate' => '1',
      'lungime' => '',
      'latime' => '',
      'inaltime' => '',
      'val_decl' => '',
      'plata_ramburs' => (int) 1,
    );
  }

  /**
   * Estimate the shipping.
   * 
   * @param type $options
   *  Shipping options.
   * @param type $service
   *  Shipping service, default to 'Standard'
   * 
   * @return \FanCourier
   */
  public function estimateShipping($options, $service = 'Standard') {
    if (!empty($this->errors)) {
      return $this;
    }

    $default_options = $this->_getShippingDefaults();
    $default_options['serviciu'] = $service;
    $options = array_merge($default_options, $options);
    $estimation = $this->request('estimation', $options);

    if (!$estimation) {
      $this->setError('No estimation returned by request in ' . __CLASS__ . ', line ' . __LINE__, 'shipping', 'notice');
      return $this;
    }

    return $estimation;
  }

  /**
   * Generic request method for both "services" and "estimation".
   * 
   * @param type $request_type
   *  A valid request string as pressent in $request_type.
   * @param type $options
   *  An optional array of post fields.
   * 
   * @return \FanCourier
   */
  private function request($request_type = NULL, $options = NULL) {
    // Validate the request type.
    if (!$request_type || !isset($this->request_type[$request_type])) {
      $this->setError('Request type param is missing.');
    }

    // Validate URL?
    $url = $this->api_url . '/' . $this->request_type[$request_type];

    // Add options, if any provided.
    if (is_array($options)) {
      $post_fields = $options;
    }
    // Make sure we have all the required params and add them to post fields.
    foreach (array('username', 'user_pass', 'client_id') as $var) {
      if (empty($this->$var)) {
        $this->setError("Invalid {$var} in " . __CLASS__ . ', line ' . __LINE__, 'request', 'notice');
        return $this;
      }
      $post_fields[$var] = $this->$var;
    }

    $post_fields['return'] = $request_type;


    $c = curl_init($url);
    curl_setopt($c, CURLOPT_POST, TRUE);
    curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);

    $result = FALSE;

    try {
      $result = curl_exec($c);
    }
    catch (Exception $e) {
      $this->setError('Curl error: "' . $e->getMessage() . '" in ' . __CLASS__ . ', line ' . __LINE__, 'request', 'notice');
      curl_close($c);
      return $this;
    }

    curl_close($c);
    return $result;
  }

  // Generic getter.
  public function set($var = NULL, $value = NULL) {
    if (!$var || $value === NULL) {
      return $this;
    }

    if (property_exists($this, $var)) {
      $this->{$var} = $value;
    }

    return $this;
  }

  // Generic setter.
  public function get($var = NULL) {
    if (!$var || !isset($this->$var)) {
      return $this;
    }

    return $this->{$var};
  }

  // Error setter.
  private function setError($message, $scope = 'general', $level = FALSE) {
    $this->errors[$scope] = $message;

    $levels = array(
      'notice' => E_USER_NOTICE,
      'warning' => E_USER_WARNING,
      'error' => E_USER_ERROR,
    );

    if ($level) {
      if (!isset($levels[$level])) {
        $level = 'notice';
      }
      trigger_error($message, $levels[$level]);
    }
  }

  // Error getter.
  public function getErrors() {
    return $this->errors;
  }

  public function hasErrors() {
    return empty($this->errors) ? FALSE : TRUE;
  }

}
