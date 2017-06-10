<?php

class User
{

  public $nick;
  private $color;
  private $identifi;

  public function __construct($identifi,$nick, $color)
  {
      $this->nick = $nick;
      $this->color = $color;
      $this->identifi = $identifi;
  }

  public function __get($name)
  {
    return $this->$name;
  }

  public function __set($name, $value)
  {
    $this->$name = $value;
    return $this->$name;
  }

}


 ?>
