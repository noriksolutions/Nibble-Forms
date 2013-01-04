<?php

/*
 * Nibble Forms library
 * Copyright (c) 2010 Luke Rotherfield, Nibble Development
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace NibbleForms;

class NibbleForm
{

    protected $action, $method, $submit_value, $fields, $sticky, $format, $message_type, $flash, $multiple_errors, $html5;
    protected $valid = true;
    protected $name = 'nibble_form';
    protected $messages = '';
    protected $data = array();
    protected $formats = array(
        'list' => array(
            'open_form' => '<ul>',
            'close_form' => '</ul>',
            'open_form_body' => '',
            'close_form_body' => '',
            'open_field' => '',
            'close_field' => '',
            'open_html' => "<li>\n",
            'close_html' => "</li>\n",
            'open_submit' => "<li>\n",
            'close_submit' => "</li>\n"
        ),
        'table' => array(
            'open_form' => '<table>',
            'close_form' => '</table>',
            'open_form_body' => '<tbody>',
            'close_form_body' => '</tbody>',
            'open_field' => "<tr>\n",
            'close_field' => "</tr>\n",
            'open_html' => "<td>\n",
            'close_html' => "</td>\n",
            'open_submit' => '<tfoot><tr><td>',
            'close_submit' => '</td></tr></tfoot>'
        )
    );
    protected static $instance;

    /**
     * @param string $action
     * @param string $submit_value
     * @param string $method
     * @param boolean $sticky
     * @param string $message_type
     * @param string $format
     * @param string $multiple_errors
     * @return \Nibble\NibbleForm
     */
    public function __construct($action, $submit_value, $html5, $method, $sticky, $message_type, $format, $multiple_errors)
    {
        $this->fields = new \stdClass();
        $this->action = $action;
        $this->method = $method;
        $this->html5 = $html5;
        $this->submit_value = $submit_value;
        $this->sticky = $sticky;
        $this->format = $format;
        $this->message_type = $message_type;
        $this->multiple_errors = $multiple_errors;
        if ($message_type == 'flash') {
            $this->flash = Flash::getInstance();
        }
        if ($message_type == 'list') {
            $this->messages = array();
        }
    }

    /**
     * Singleton method
     * @param string $action
     * @param string $method
     * @param boolean $sticky
     * @param string $submit_value
     * @param string $message_type
     * @param string $format
     * @param string $multiple_errors
     * @return \Nibble\NibbleForm
     */
    public static function getInstance($action = '/', $html5 = true, $method = 'post', $submit_value = 'Submit', $sticky = true, $message_type = 'list', $format = 'list', $multiple_errors = false)
    {
        if (!self::$instance) {
            self::$instance = new NibbleForm($action, $submit_value, $html5, $method, $sticky, $message_type, $format, $multiple_errors);
        }
        return self::$instance;
    }

    /**
     * Autoloader for nibble forms
     * @param string $class
     */
    public static function nibbleLoader($class)
    {
        $namespace = explode('\\', $class);
        if ($namespace[0] == "NibbleForms") {
            array_shift($namespace);
        }
        require dirname(__FILE__) . '/' . implode('/', $namespace) . '.php';
    }

    /**
     * Add a field to the form instance
     * @param string $field_name
     * @param string $type
     * @param array $attributes
     * @param boolean $overwrite
     * @return boolean
     */
    public function addField($field_name, $type = 'text', array $attributes = array(), $overwrite = false)
    {
        $namespace = "\\NibbleForms\\Field\\" . ucfirst($type);
        if (isset($attributes['label'])) {
            $label = $attributes['label'];
        } else {
            $label = $field_name;
        }
        $field_name = Useful::slugify($field_name, '_');
        if ($this->fields->$field_name && !$overwrite) {
            return false;
        }
        $this->fields->$field_name = new $namespace($label, $attributes);
        return $this->fields->$field_name;
    }

    /**
     * Set the name of the form
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Add data to populate the form
     * @param array $data
     */
    public function addData(array $data)
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Validate the submitted form
     * @return boolean
     */
    public function validate()
    {
        if (strtolower($this->method) == 'get') {
            $form_data = $_GET[$this->name];
        } else {
            $form_data = $_POST[$this->name];
        }
        if ((isset($_SESSION['token']) && $form_data['token'] !== $_SESSION['token'][$this->name]) || !isset($_SESSION['token']) || !isset($form_data['token'])) {
            $this->setMessages('CRSF token invalid', 'CRSF error');
            $this->valid = false;
            return false;
        }
        $_SESSION['token'] = array();
        if ($this->sticky) {
            $this->addData($form_data);
        }
        foreach ($this->fields as $key => $value) {
            if (!$value->validate((isset($form_data[$key]) ? $form_data[$key] : (isset($_FILES[$this->name][$key]) ? $_FILES[$this->name][$key] : '')))) {
                $this->valid = false;
                return false;
            }
        }
        return true;
    }

    /**
     * Render the entire form including submit button, errors, form tags etc
     * @return string
     */
    public function render()
    {
        $fields = '';
        $error = $this->valid ? '' : '<p class="error">Sorry there were some errors in the form, problem fields have been highlighted</p>';
        $format = (object) $this->formats[$this->format];

        foreach ($this->fields as $key => $value) {
            $format = (object) $this->formats[$this->format];
            $temp = isset($this->data[$key]) ? $value->returnField($key, $this->data[$key]) : $value->returnField($key);
            $fields .= $format->open_field;
            if ($temp['label']) {
                $fields .= $format->open_html . $temp['label'] . $format->close_html;
            }
            if (isset($temp['messages'])) {
                foreach ($temp['messages'] as $message) {
                    if ($this->message_type == 'inline') {
                        $fields .= "$format->open_html <p class=\"error\">$message</p> $format->close_html";
                    } else {
                        $this->setMessages($message, $key);
                    }
                    if (!$this->multiple_errors) {
                        break;
                    }
                }
            }
            $fields .= $format->open_html . $temp['field'] . $format->close_html . $format->close_field;
        }

        if (!empty($this->messages)) {
            $this->buildMessages();
        } else {
            $this->messages = false;
        }
        self::$instance = false;
        return <<<FORM
            $error
            $this->messages
            <form class="form" action="$this->action" method="$this->method" enctype="multipart/form-data">
              $format->open_form
                $format->open_form_body
                  {$this->setToken()}
                  $fields
                $format->close_form_body
                $format->open_submit
                  <input type="submit" name="submit" value="$this->submit_value" />
                $format->close_submit
              $format->close_form
            </form>
FORM;
    }

    /**
     * Returns the HTML for a specific form field ususally in the form of input tags
     * @param string $name
     * @return string
     */
    public function renderField($name)
    {
        return $this->getFieldData($name, 'field');
    }

    /**
     * Returns the HTML for a specific form field's label
     * @param string $name
     * @return string
     */
    public function renderLabel($name)
    {
        return $this->getFieldData($name, 'label');
    }

    /**
     * Returns the error string for a specific form field
     * @param string $name
     * @return string
     */
    public function renderError($name)
    {
        $error_string = '';
        foreach ($this->getFieldData($name, 'messages') as $error) {
            $error_string .= "<li>$error</li>";
        }
        return $error_string === '' ? false : "<ul>$error_string</ul>";
    }

    /**
     * Returns the entire HTML structure for a form field
     * @param string $name
     * @return string
     */
    public function renderRow($name)
    {
        $row_string = $this->renderError($name);
        $row_string .= $this->renderLabel($name);
        $row_string .= $this->renderField($name);
        return $row_string;
    }

    /**
     * Returns HTML for all hidden fields including crsf protection
     * @return string
     */
    public function renderHidden()
    {
        $fields = array($this->setToken());
        foreach ($this->fields as $name => $field) {
            if (get_class($field) == 'Hidden') {
                if (isset($this->data[$name])) {
                    $fields_data = $field->returnField($this->name, $name, $this->data[$name]);
                } else {
                    $fields_data = $field->returnField($this->name, $name);
                }
                $fields[] = $field_data['field'];
            }
        }

        return implode("\n", $fields);
    }

    /**
     * Returns HTML string for all errors in the form
     * @return string
     */
    public function renderErrors()
    {
        $error_string = '';
        foreach (array_keys($this->fields) as $name) {
            foreach ($this->getFieldData($name, 'messages') as $error) {
                $error_string .= "<li>$error</li>\n";
            }
        }
        return $error_string === '' ? false : "<ul>$error_string</ul>";
    }

    /**
     * Returns the HTML string for opening a form with the correct enctype, action and method
     * @return string
     */
    public function openForm()
    {
        $multipart = false;
        foreach ($this->fields as $field) {
            if (get_class($field) == 'File') {
                $multipart = true;
            }
        }
        return "<form class=\"form\" action=\"$this->action\" method=\"$this->method\"" . ($multipart ? 'enctype="multipart/form-data"' : '') . ">";
    }

    /**
     * Return close form tag
     * @return string
     */
    public function closeForm()
    {
        return "</form>";
    }

    /**
     * Adds a message string to the class messages array or as a flash message
     * @param string $message
     * @param string $title
     */
    private function setMessages($message, $title)
    {
        $title = preg_replace('/_/', ' ', ucfirst($title));
        if ($this->message_type == 'flash') {
            $this->flash->message(ucfirst($message), $title, 0, true);
        } elseif ($this->message_type == 'list') {
            $this->messages[] = array('title' => $title, 'message' => ucfirst($message));
        }
    }

    /**
     * Sets the messages array as an HTML string
     */
    private function buildMessages()
    {
        $messages = '<ul class="error">';
        foreach ($this->messages as $message_array) {
            $messages .= sprintf('<li>%s: %s</li>%s', ucfirst(preg_replace('/_/', ' ', $message_array['title'])), ucfirst($message_array['message']), "\n");
        }
        $this->messages = $messages . '</ul>';
    }

    /**
     * Gets a specific field HTML string from the field class
     * @param string $name
     * @param string $key
     * @return string
     */
    private function getFieldData($name, $key)
    {
        if (!$this->checkField($name)) {
            return false;
        }
        $field = $this->fields->$name;
        if (isset($this->data[$name])) {
            $field = $field->returnField($this->name, $name, $this->data[$name]);
        } else {
            $field = $field->returnField($this->name, $name);
        }
        return $field[$key];
    }

    /**
     * Check if a field exists
     * @param string $field
     * @return boolean
     */
    private function checkField($field)
    {
        return isset($this->fields->$field);
    }

    /**
     * Creates a new CRSF token
     * @return string
     */
    private function setToken()
    {
        if (!isset($_SESSION['token'])) {
            $_SESSION['token'] = array();
        }
        $_SESSION['token'][$this->name] = \NibbleForms\Useful::randomString(20);
        return sprintf('<input type="hidden" value="%s" name="%s[token]" />', $_SESSION['token'][$this->name], $this->name);
    }

}

spl_autoload_register(__NAMESPACE__ . "\NibbleForm::nibbleLoader");

class Useful
{

    /**
     * Strip out all empty characters from a string
     * @param string $val
     * @return string
     */
    public static function stripper($val)
    {
        foreach (array(' ', '&nbsp;', '\n', '\t', '\r') as $strip)
            $val = str_replace($strip, '', (string) $val);
        return $val === '' ? false : $val;
    }

    /**
     * Slugify a string using a specified replacement for empty characters
     * @param string $text
     * @param string $replacement
     * @return string
     */
    public static function slugify($text, $replacement = '-')
    {
        return strtolower(trim(preg_replace('/\W+/', $replacement, $text), '-'));
    }

    /**
     * Return a random string of specified length
     * @param int $length
     * @param string $return
     * @return string
     */
    public static function randomString($length = 10, $return = '')
    {
        $string = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890';
        while ($length-- > 0)
            $return .= $string[mt_rand(0, strlen($string) - 1)];
        return $return;
    }

}
