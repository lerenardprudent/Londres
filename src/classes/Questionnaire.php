<?php

require_once('Constants.php');

class Questionnaire extends ArrayObject {
  private $const_elems;
  private $C;
  private $initial_pos;
  private $final_pos;
  
  function __construct()
  {
    $file_path = 'includes/questionnaire.json';
    $this->const_elems = json_decode(utf8_encode(file_get_contents($file_path)));
    $this->C = new Constants();
    $this->initial_pos = "0" . $this->C['CURR_POS_SEPARATOR'] . "0";
    
    $x = get_object_vars($this->const_elems);
    $y = $x[count($x)-1];
    $this->final_pos = (count($x)-1) . $this->C['CURR_POS_SEPARATOR'] . (count($y)-1);
  }
  
  public function offsetGet($key)
  {
    if (isset($this->const_elems->{$key}))
      return $this->const_elems->{$key};
    die("Config element '".$key."' not found.");
  }
  
  public function get_initial_pos()
  {
    return $this->initial_pos;
  }
  
  public function is_first_screen($pos)
  {
    return $pos == $this->initial_pos;
  }
  
  public function get_final_pos()
  {
    return $this->final_pos;
  }
  
  public function is_final_screen($pos)
  {
    return $pos == $this->final_pos;
  
  }
}
?>