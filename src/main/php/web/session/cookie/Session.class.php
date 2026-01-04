<?php namespace web\session\cookie;

use web\session\{Persistence, SessionInvalid};

/**
 * A session stored in a cookie
 *
 * @see   web.session.CookieBased
 */
class Session extends Persistence {
  private $values;
  private $id= null;

  /**
   * Creates a new cookie-based session
   *
   * @param  web.session.Sessions $sessions
   * @param  [:var] $values
   * @param  int $expires
   */
  public function __construct($sessions, $values, $expires) {
    parent::__construct($sessions, false, $expires);
    $this->values= $values;
  }

  /** @return string */
  public function id() { return $this->id ?? $this->id= $this->sessions->serialize($this->values, $this->expires); }

  /** @return void */
  public function destroy() {
    $this->expires= time() - 1;
    $this->detached= false;
  }

  /**
   * Returns all session keys
   *
   * @return string[]
   */
  public function keys() {
    return array_keys($this->values);
  }

  /**
   * Registers a value - writing it to the session
   *
   * @param  string $name
   * @param  var $value
   * @return void
   * @throws web.session.SessionInvalid
   */
  public function register($name, $value) {
    if (time() >= $this->expires) {
      throw new SessionInvalid($this->id());
    }

    $this->values[$name]= [$value];
    $this->detached= true;
    $this->id= null;
  }

  /**
   * Retrieves a value - reading it from the session
   *
   * @param  string $name
   * @param  var $default
   * @return var
   * @throws web.session.SessionInvalid
   */
  public function value($name, $default= null) {
    if (time() >= $this->expires) {
      throw new SessionInvalid($this->id());
    }

    return $this->values[$name][0] ?? $default;
  }

  /**
   * Removes a value - deleting it from the session
   *
   * @param  string $name
   * @return bool
   * @throws web.session.SessionInvalid
   */
  public function remove($name) {
    if (time() >= $this->expires) {
      throw new SessionInvalid($this->id());
    }

    if (!isset($this->values[$name])) return false;
    unset($this->values[$name]);
    $this->detached= true;
    $this->id= null;
    return true;
  }
}