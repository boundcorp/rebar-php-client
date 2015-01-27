<?php

/**
 * This class contains regularly used tools
 *
 * @author InteliClic
 */
class coreInterface {

    /**
     * Form options
     */
    public $form = false;

    /**
     * Fields for the cart
     */
    public $fields = false;

    /**
     * Errors for the cart
     */
    public $errors = false;

    /**
     * Options for the cart
     */
    public $options = false;

    /**
     * Current Step
     */
    public $step = '';

    /**
     * Options for the cart
     */
    public $cards = false;

    function __construct() {
        $this->form = new stdClass();
        $this->fields = new stdClass();
        $this->errors = new stdClass();
        $this->options = new stdClass();
        $this->cards = new stdClass();
        $this->setAcceptedCards();
    }

    /**
     * Send an error report to specific people
     * @param array $error containing error information
     * @param array $data containing api information
     * @return bool
     */
    function apiErrorReport($error, $data) {
        global $config;

        ob_start();

        echo '<pre>';
        print_r($error);
        echo '</pre>';

        $error_str = ob_get_contents();
        ob_end_clean();

        $content = <<<EOC
<html>
<head></head>
<body>
<p>An error occurred when trying to make a request to the an external API.</p>

<p>API Called: {$data['api']}<br />
API Method: {$data['method']}</p>

<p>API Exception<br />
=======================================</p>
<br />
$error_str
</body>
</html>
EOC;

        $data['to'] = $config['error_to'];
        $data['from'] = $config['error_from'];
        $data['subject'] = 'External API Error';
        $data['content'] = $content;

        return $this->sendEmail($data);
    }

    /**
     * Send an error report to specific people
     * @param array $error containing error information
     * @param array $data containing api information
     * @return bool
     */
    function reportError($error) {
        global $config;

        $page = $_SERVER['PHP_SELF'];
        $referer = $_SERVER['HTTP_REFERER'];
        $url = $_SERVER['REQUEST_URI'];

        ob_start();

        echo '<pre>';
        print_r($error);
        echo '</pre>';

        $error_str = ob_get_contents();
        ob_end_clean();

        $content = <<<EOC
<html>
<head></head>
<body>
<p>An error occurred.</p>

<p>Page: $page<br />
Full URL: $url<br />
Referer: $referer</p>

<p>Exception<br />
=======================================</p>
<br />
$error_str
</body>
</html>
EOC;

        $data['to'] = $config['error_to'];
        $data['from'] = $config['error_from'];
        $data['subject'] = 'Internal Error Report';
        $data['content'] = $content;

        return $this->sendEmail($data);
    }

    /**
     * Builds the email objects and sends it out
     * @param string $to
     * @param array $data
     * @param string $attachment
     * @return bool
     */
    function sendEmail($data, $attachment = false) {
        $msg = new Email($data['to'], $data['from'], $data['subject']);
        $msg->TextOnly = false;
        $msg->Content = $data['content'];

        if ($attachment) {
            $msg->Attach($attachment['file_path'] . '/' . $attachment['file_name'], $attachment['file_type']);
        }

        $SendSuccess = $msg->Send();

        return $SendSuccess;
    }

    /**
     * Detect fields and add them to the fields object
     * @return boolean;
     */
    function detectFields() {
        global $config;
        $fields = (array) $_SESSION['cart']['fields'];
        if (count($fields) > 0) {
            foreach ($fields as $key => $value)
                $this->fields->$key = (in_array($key, $config['payment_fields_exclude']) AND $config['data_encrypt']) ? $this->decryptData($value) : $value;
        }
    }

    /**
     * Detect Exit Popup Override
     */
    function detectExitOverride() {
        if (isset($_GET['exit']) OR $_SESSION['exit']) {
            if (isset($_GET['exit']))
                $_SESSION['exit'] = $_GET['exit'];
            unset($this->options->exitpop);
        }
    }

    /**
     * Set Accepted CreditCards
     */
    function setAcceptedCards() {
        global $config;
        $cards = $this->getCreditCards();
        if (count($cards) > 0 AND is_array($cards)) {
            foreach ($cards as $id => $card) {
                if (in_array($card['name'], $config['accepted_cards'])) {
                    $this->cards->{$card['id']} = $card;
                }
            }
        }
    }

    /**
     * Set Session Data
     */
    function setSessionData() {
        global $config;
        $fields = (array) $this->fields;
        if (count($fields) > 0) {
            foreach ($fields as $key => $value) {
                if (in_array($key, $config['payment_fields_exclude'])) {
                    if ($key == 'creditCardVerificationNumber') {
                        if ($config['data_cache'])
                            $_SESSION['cart']['fields'][$key] = $this->encryptData($value);
                    } else if ($config['data_encrypt']) {
                        $_SESSION['cart']['fields'][$key] = $this->encryptData($value);
                    }
                } else {
                    $_SESSION['cart']['fields'][$key] = $value;
                }
            }
        }
    }

    /**
     * Detect Kount & set SessionID
     */
    function detectKount() {
        global $config;
        if (empty($this->fields->sessionId)) {
            if (!empty($_SESSION['kount_session_id'])) {
                $this->fields->sessionId = $_SESSION['kount_session_id'];
            } else if (!empty($_COOKIE['kount_session_id'])) {
                $this->fields->sessionId = $_COOKIE['kount_session_id'];
            } else {
                $base = $this->fields->idLog;
                $sessionId = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($config['kount_key']), $this->fields->idLog, MCRYPT_MODE_CBC, md5(md5($config['kount_key']))));
                // $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($config['kount_key']), base64_decode($sessionId), MCRYPT_MODE_CBC, md5(md5($config['kount_key']))), "\0");
                $this->fields->sessionId = $sessionId;
                $_SESSION['kount_session_id'] = $this->fields->sessionId;
                setcookie('kount_session_id', $this->fields->sessionId, $config['cookie_expiry'], $config['cookie_path'], $config['cookie_domain']);
            }
        }
    }

    /**
     * Encrypt Sensitive Data
     * @global array $config
     * @param mixed $data
     * @return mixed
     */
    function encryptData($data) {
        global $config;
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($config['data_key']), $value, MCRYPT_MODE_CBC, md5(md5($config['data_key']))));
            }
        } else {
            $data = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($config['data_key']), $data, MCRYPT_MODE_CBC, md5(md5($config['data_key']))));
        }
        return $data;
    }

    /**
     * Decrypt Sensitive Data
     * @global array $config
     * @param mixed $data
     * @return mixed
     */
    function decryptData($data) {
        global $config;
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($config['data_key']), base64_decode($value), MCRYPT_MODE_CBC, md5(md5($config['data_key']))), "\0");
            }
        } else {
            $data = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($config['data_key']), base64_decode($data), MCRYPT_MODE_CBC, md5(md5($config['data_key']))), "\0");
        }
        return $data;
    }

    /**
     * Determine visibility of shipping fields
     * @return boolean;
     */
    function showShipping($force = null) {
        global $config;
        if (!is_null($force))
            $this->form->showShipping = $force;
        if (empty($this->fields->shipToFirstName) OR
            empty($this->fields->shipToLastName) OR
            empty($this->fields->shipToAddress1) OR
            empty($this->fields->shipToCity) OR
            empty($this->fields->shipToState) OR
            empty($this->fields->shipToPostalCode) OR
            empty($this->fields->shipToCountry) OR
            empty($this->fields->shipToPhone) OR
            empty($this->fields->email)) {
            if ($this->form->shippingEditable) {
                $this->form->showShipping = true;
            } else {
                header("Location:" . reset($config['funnel']));
                exit();
            }
        }
    }

    /**
     * Detect if there are errors and alert them
     * @return boolean;
     */
    function alertErrors() {
        global $lang;

        if (count((array) $this->errors) > 0) {
            if (!empty($this->errors->question)) {
                $_SESSION['question'][] = $this->errors->question;
                unset($this->errors->question);
            }

            if (count((array) $this->errors) > 0) {
                foreach ((array) $this->errors as $error) {
                    $errorList .= "<li>$error</li>";
                }
                $_SESSION['alerts'][] = $lang['error']['input_errors'] . "<br /><br /><ul>$errorList</ul><br />" . $lang['error']['input_review'];
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Show Notice Bar above content
     * @return boolean;
     */
    function showNoticeBar() {
        if ($this->options->header['notice']) {
            if ($_SESSION['notified'] <= 5) {
                $_SESSION['notified'] = $_SESSION['notified'] + 1;
                return true;
            } else {
                return false;
            }
            $this->options->header['notice'] = showNoticeBar();
        }
    }

    /**
     * Deterime the number of days for shipment to arrive
     * @return boolean;
     */
    function getShippingDays() {
        global $config;
        // Check how many countries we have in the config and set shipping days and duration appropriately.
        $first = array_keys($config['countries']);
        $first = strtolower(array_shift($first));
        if (count($config['countries']) == 1) {
            $shippingDays = $config['ship'][$first];
        } else {
            if (!empty($this->fields->country)) {
                $shippingDays = $config['ship'][strtolower($this->fields->country)];
            } else {
                $shippingDays = $config['ship'][$first];
            }
        }

        $this->form->shippingDays = $shippingDays;
    }

    /**
     * Send Postback to HasOffers
     * @return boolean;
     */
    function sendPostbackHasOffers($url) {
        $retries = 10;
        $success = false;
        $error = '';
        while (!$success AND $retries > 0) {
            $fields = array();
            $result = file_get_contents($url);
            $rows = explode(';', $result);
            foreach ($rows as $row) {
                parse_str($row, $field);
                if ($field['success'] === 'true') {
                    $success = true;
                    break(2);
                }
                if (strpos($field['err_msg'], 'Duplicate recorded') === 0) {
                    $success = true;
                    break(2);
                } else {
                    $error = $field['err_msg'];
                }
            }
            $retries = $retries - 1;
        }

        if ($retries === 0) {
            $data = array('api' => 'Postback Pixel');
            $this->apiErrorReport($error, $data);
        }
    }

    /**
     * Get the file without the extension
     * @return boolean;
     */
    function currentFileName() {
        if (array_key_exists('PATH_INFO', $_SERVER) === true) {
            return preg_replace("/\\.[^.\\s]{3,4}$/", "", $_SERVER['PATH_INFO']);
        }
        $whatToUse = basename(__FILE__); // see below
        $filename = substr($_SERVER['PHP_SELF'], strpos($_SERVER['PHP_SELF'], $whatToUse) + strlen($whatToUse));
        return preg_replace("/\\.[^.\\s]{3,4}$/", "", $filename);
    }

    /*

      FORM VALIDATION FUNCTIONS

     */

    /**
     * Validate Email Address
     *
     * @param string $email
     * @return bool
     */
    function validateEmail($email) {
        $isValid = true;
        $atIndex = strrpos($email, "@");

        if (is_bool($atIndex) && !$atIndex) {

            $isValid = false;
        } else {

            $domain = substr($email, $atIndex + 1);
            $local = substr($email, 0, $atIndex);
            $localLen = strlen($local);
            $domainLen = strlen($domain);

            if ($localLen < 1 || $localLen > 64) {
                // local part length exceeded
                $isValid = false;
            } else if ($domainLen < 1 || $domainLen > 255) {
                // domain part length exceeded
                $isValid = false;
            } else if ($local[0] == '.' || $local[$localLen - 1] == '.') {
                // local part starts or ends with '.'
                $isValid = false;
            } else if (preg_match('/\\.\\./', $local)) {
                // local part has two consecutive dots
                $isValid = false;
            } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                // character not valid in domain part
                $isValid = false;
            } else if (preg_match('/\\.\\./', $domain)) {
                // domain part has two consecutive dots
                $isValid = false;
            } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local))) {
                // character not valid in local part unless
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local))) {
                    $isValid = false;
                }
            }

            /* if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))){
              // domain not found in DNS
              $isValid = false;
              } */

            /*            if ($isValid && !(checkdnsrr($domain, "MX"))) {
              // domain not found in DNS
              $isValid = false;
              }
             */
        }

        return $isValid;
    }

    function validateCreditCard() {
        $cardNumber = trim($this->fields->creditCardNumber);
        // Check for a tokenized CC number, if found return true;
        if (strpos($this->fields->creditCardNumber, 'XXXX-XXXX-') === 0) {
            return true;
        } else {
            $cardNumber = preg_replace("/[^0-9]/", "", $cardNumber);
            $cardType = $this->getCreditCardType($cardNumber);
            if ($cardType['enabled']) {
                $ccLength = explode(',', $cardType['length']);
                $validlength = false;
                foreach ($ccLength as $length) {
                    if (strlen($cardNumber) == $length) {
                        $validlength = true;
                        break;
                    }
                }

                if ($validlength) {
                    $isEven = true;
                    $rightIndex = strlen($cardNumber) - 1;
                    $total = 0;
                    for ($index = $rightIndex; $index >= 0; $index--) {
                        $isEven = !$isEven;
                        $currentNumber = $cardNumber[$index];
                        if ($isEven)
                            $currentNumber *= 2;

                        if ($currentNumber > 9) {
                            $currentNumber = (string) $currentNumber;
                            $currentNumber = $currentNumber[0] + $currentNumber[1];
                        }

                        $total += $currentNumber;
                    }

                    return (($total % 10) == 0);
                }
            }
        }
        return false;
    }

    function validateCreditCardExpiry() {
        global $config;

        if (!empty($this->fields->creditCardExpirationYear) AND ! empty($this->fields->creditCardExpirationMonth)) {
            $d1 = new DateTime();
            $d1->setDate($this->fields->creditCardExpirationYear, $this->fields->creditCardExpirationMonth, 0);
            $lastDay = $d1->format('t');
            $d1->setDate($this->fields->creditCardExpirationYear, $this->fields->creditCardExpirationMonth, $lastDay);
            $d2 = new DateTime();
            // $d2->add(new DateInterval("P3M")); // Add 3 months.
            $d3 = $d2->diff($d1);
            $diff = number_format($d3->format('%R%a'));
            return ($diff > 0);
            // Legacy
            $month = $this->fields->creditCardExpirationMonth + 1;
            $expiry_str = mktime('00', '00', '00', $month, 1, $this->fields->creditCardExpirationYear);
            $today_str = time();
            return ($expiry_str > $today_str);
        } else {
            return false;
        }
    }

    function validateCreditCardCVV() {
        $cardNumber = trim($this->fields->creditCardNumber);
        if (!empty($cardNumber)) {
            $cardType = $this->getCreditCardType($cardNumber);
            if (strlen($this->fields->creditCardVerificationNumber) == $cardType['cvvLength'])
                return true;
            else
                return false;
        }
        return false;
    }

    /**
     * @todo pull from database, use cache to hold the results
     * @return boolean
     */
    function getCreditCardType() {
        $cardType = 0;
        $cards = $this->getCreditCards();
        // Check for a tokenized CC number, if found return true;
        if (strpos($this->fields->creditCardNumber, 'XXXX-XXXX-') === 0) {
            // this area needs some work.. I don't think we need to check the ultracart side, just the local fields.
            // need to get creditCardType from field at this point.
            foreach ($cards as $type => $card) {
                $result = array_search($this->fields->creditCardType, $card);
                if ($result == 'name' OR $result == 'card') {
                    $cardType = $type;
                    break;
                }
            }
        } else {
            foreach ($cards as $card) {
                $ccPrefix = explode(',', $card['startWith']);
                foreach ($ccPrefix as $prefix) {
                    $prefixLength = strlen($prefix);
                    if (substr($this->fields->creditCardNumber, 0, $prefixLength) == $prefix) {
                        $cardType = $card['id'];
                        break 2;
                    }
                }
                if ($cardType > 0)
                    break;
            }
        }

        if ($cardType > 0) {
            return $cards[$cardType];
        } else {
            return false;
        }
    }

    function getCreditCardByName($cardName) {
        $types = $this->getCreditCards();
        foreach ($types as $id => $type)
            if ($type['name'] == $cardName)
                return $type;
        return false;
    }

    function getCreditCards() {
        $types = array();
        $types[1] = array('id' => '1', 'name' => 'visa', 'card' => 'Visa', 'startWith' => '4', 'length' => '13,16', 'isDefault' => 'Y', 'enabled' => true, 'cvvLength' => '3');
        $types[2] = array('id' => '2', 'name' => 'visa_electron', 'card' => 'Visa Electron', 'startWith' => '4026,417500,4508,4844,4913,4917', 'length' => '16', 'isDefault' => 'Y', 'enabled' => false, 'cvvLength' => '3');
        $types[3] = array('id' => '3', 'name' => 'mastercard', 'card' => 'Master Card', 'startWith' => '51,52,53,54,55', 'length' => '16', 'isDefault' => 'Y', 'enabled' => true, 'cvvLength' => '3');
        $types[4] = array('id' => '4', 'name' => 'maestro', 'card' => 'Maestro', 'startWith' => '5018,5020,5038,6304,6759,6761,6762,6763', 'length' => '12,13,14,15,16,17,18,19', 'isDefault' => 'Y', 'enabled' => false, 'cvvLength' => '3');
        $types[5] = array('id' => '5', 'name' => 'amex', 'card' => 'AMEX', 'startWith' => '34,37', 'length' => '15', 'isDefault' => 'Y', 'enabled' => false, 'cvvLength' => '4');
        $types[6] = array('id' => '6', 'name' => 'diners', 'card' => 'Diners Club / Carte Blanche', 'startWith' => '300,301,302,303,304,305', 'length' => '14', 'isDefault' => 'N', 'enabled' => false, 'cvvLength' => '3');
        $types[6] = array('id' => '6', 'name' => 'diners_club_international', 'card' => 'Diners Club International', 'startWith' => '36', 'length' => '14', 'isDefault' => 'N', 'enabled' => false, 'cvvLength' => '3');
        $types[7] = array('id' => '7', 'name' => 'discover', 'card' => 'Discover', 'startWith' => '6011', 'length' => '16', 'isDefault' => 'N', 'enabled' => true, 'cvvLength' => '3');
        $types[8] = array('id' => '8', 'name' => 'jcb', 'card' => 'JCB', 'startWith' => '2131,1800', 'length' => '15', 'isDefault' => 'N', 'enabled' => false, 'cvvLength' => '3');
        $types[9] = array('id' => '9', 'name' => 'jcb', 'card' => 'JCB', 'startWith' => '3', 'length' => '16', 'isDefault' => 'N', 'enabled' => false, 'cvvLength' => '3');
        $types[10] = array('id' => '10', 'name' => 'laser', 'card' => 'Laser', 'startWith' => '6304,670,67069,6771', 'length' => '16,17,18,19', 'isDefault' => 'N', 'enabled' => false, 'cvvLength' => '3');
        return $types;
    }

}
