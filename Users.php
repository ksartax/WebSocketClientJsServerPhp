<?php

include_once 'User.php';

class Users
{

  private $users;

  public function __construct()
  {
    $this->users = [];
  }

  public function __get($name)
  {
    return $this->$name;
  }

  public function addUser($identifi, $nick)
  {
    if($this->searchUsersByIdent($identifi)){
      return 0;
    }
    $user = new User($identifi, $nick, $this->setColor());
    $this->users[] = $user;
    return $user;
  }

  private function setColor()
  {
    $pom = $this->colorMaps();
    foreach ($this->users as $key => $value) {
        unset($pom[$value->color]);
    }
    if(empty($pom)){
      return '#FFFFFF';
    }

    return $pom[0];
  }

  public function removeUser($identifi)
  {
    foreach ($this->users as $key => $value) {
      if($value->identifi == $identifi){
          unset($this->users[$key]);
          $this->users = array_values($this->users);
          return true;
      }
    }
    return false;
  }

  private function colorMaps()
  {
    return array(
      '#C0C0C0',
      '#FF00FF',
      '#FF00FF',
      '#FF00FF',
      '#FF00FF',
      '#FF00FF',
      '#008000',
      '#FFFF00',
      '#FFFF00',
      '#FFFF00',
      '#000080',
      '#0000FF',
      '#008080'
    );
  }

  public function searchUsersByIdent($identifi)
  {
    foreach ($this->users as $key => $value) {
      if($value->identifi == $identifi){

        return $value;
      }
    }
    return false;
  }


}




 ?>
