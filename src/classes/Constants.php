<?php

class Constants extends ArrayObject {
  private $const_elems;
  
  function __construct()
  {
    $file_path = 'includes/constants.json';
    $this->const_elems = json_decode(utf8_encode(file_get_contents($file_path)));
  }
  
  public function offsetGet($key)
  {
    if (isset($this->const_elems->{$key}))
      return $this->const_elems->{$key};
    die("Config element '".$key."' not found.");
  }
}
?>