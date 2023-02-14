<?php namespace web\session\unittest;

use lang\IllegalArgumentException;
use unittest\{Assert, Test, Expect, Values};
use util\Secret;
use web\io\{TestInput, TestOutput};
use web\session\{ISession, Cookies, CookieBased, SessionInvalid};
use web\{Request, Response};

class CookieBasedTest {

  /** @return web.session.Sessions */
  protected function fixture() { return new CookieBased(new Secret('tlw3/ELaLfu3kmpzQJ0MDCdRG2b8Le+X')); }

  /**
   * Creates a request
   *
   * @param  string $cookie
   * @return web.Request
   */
  protected function request($cookie= null) {
    return new Request(new TestInput('GET', '/', $cookie ? ['Cookie' => $cookie] : []));
  }

  /**
   * Creates a response
   *
   * @return web.Response
   */
  protected function response() {
    return new Response(new TestOutput());
  }

  #[Test]
  public function create() {
    $sessions= $this->fixture();
    Assert::instance(ISession::class, $sessions->create());
  }

  #[Test]
  public function named() {
    $sessions= $this->fixture();
    Assert::equals('SESS', $sessions->named('SESS')->name());
  }

  #[Test]
  public function lasting() {
    $sessions= $this->fixture();
    Assert::equals(43200, $sessions->lasting(43200)->duration());
  }

  #[Test]
  public function open() {
    $sessions= $this->fixture();

    $session= $sessions->create();
    $session->register('id', 'Test');
    $session->close();

    $session= $sessions->open($session->id());
    Assert::equals('Test', $session->value('id'));
  }

  #[Test]
  public function open_non_existant() {
    Assert::null($this->fixture()->open('@non-existant@'));
  }

  #[Test]
  public function locate() {
    $sessions= $this->fixture();

    $session= $sessions->create();
    $session->register('id', 'Test');
    $session->transmit($this->response());

    $session= $sessions->locate($this->request($sessions->name().'='.$session->id()));
    Assert::equals('Test', $session->value('id'));
  }

  #[Test]
  public function locate_invalid() {
    $sessions= $this->fixture();

    $session= $sessions->create();
    $session->destroy();
    $session->transmit($this->response());

    Assert::null($sessions->locate($this->request($sessions->name().'='.$session->id())));
  }

  #[Test]
  public function locate_non_existant() {
    $sessions= $this->fixture();
    Assert::null($sessions->locate($this->request($sessions->name().'=@non-existant@')));
  }

  #[Test]
  public function attach() {
    $sessions= $this->fixture();
    $response= $this->response();

    $session= $sessions->create();
    $session->register('id', 'Test');
    $session->transmit($response);

    $cookie= $response->cookies()[0];
    Assert::equals([$sessions->name() => $session->id()], [$cookie->name() => $cookie->value()]);
  }

  #[Test]
  public function detach() {
    $sessions= $this->fixture();
    $response= $this->response();

    $session= $sessions->create();
    $session->destroy();
    $session->transmit($response);

    $cookie= $response->cookies()[0];
    Assert::equals([$sessions->name() => ''], [$cookie->name() => $cookie->value()]);
  }

  #[Test]
  public function detach_uses_path() {
    $sessions= $this->fixture()->via((new Cookies())->path('/testing'));
    $response= $this->response();

    $session= $sessions->create();
    $session->destroy();
    $session->transmit($response);

    $cookie= $response->cookies()[0];
    Assert::equals('/testing', $cookie->attributes()['path']);
  }

  #[Test]
  public function detach_uses_domain() {
    $sessions= $this->fixture()->via((new Cookies())->domain('example.org'));
    $response= $this->response();

    $session= $sessions->create();
    $session->destroy();
    $session->transmit($response);

    $cookie= $response->cookies()[0];
    Assert::equals('example.org', $cookie->attributes()['domain']);
  }

  #[Test]
  public function valid() {
    $session= $this->fixture()->create();
    Assert::true($session->valid());
  }

  #[Test]
  public function read_write() {
    $session= $this->fixture()->create();
    $session->register('name', 'value');
    Assert::equals('value', $session->value('name'));
  }

  #[Test]
  public function read_non_existant() {
    $session= $this->fixture()->create();
    Assert::null($session->value('name'));
  }

  #[Test]
  public function read_non_existant_returns_default() {
    $session= $this->fixture()->create();
    Assert::equals('Default value', $session->value('name', 'Default value'));
  }

  #[Test]
  public function remove() {
    $session= $this->fixture()->create();
    $session->register('name', 'value');
    Assert::true($session->remove('name'));
    Assert::null($session->value('name'));
  }

  #[Test]
  public function remove_non_existant() {
    $session= $this->fixture()->create();
    Assert::false($session->remove('name'));
    Assert::null($session->value('name'));
  }

  #[Test]
  public function read_write_with_two_session_instances() {
    $sessions= $this->fixture();

    $session= $sessions->create();
    $session->register('name', 'value');
    $session->transmit($this->response());

    $session= $sessions->open($session->id());
    $value= $session->value('name');

    Assert::equals('value', $value);
  }

  #[Test]
  public function no_longer_valid_after_having_been_destroyed() {
    $session= $this->fixture()->create();
    $session->destroy();
    Assert::false($session->valid());
  }

  #[Test, Expect(SessionInvalid::class)]
  public function cannot_read_after_destroying() {
    $session= $this->fixture()->create();
    $session->destroy();
    $session->value('any');
  }

  #[Test, Expect(SessionInvalid::class)]
  public function cannot_write_after_destroying() {
    $session= $this->fixture()->create();
    $session->destroy();
    $session->register('any', 'value');
  }

  #[Test, Expect(SessionInvalid::class)]
  public function cannot_remove_after_destroying() {
    $session= $this->fixture()->create();
    $session->destroy();
    $session->remove('any');
  }

  #[Test]
  public function keys_initially_empty() {
    $session= $this->fixture()->create();
    Assert::equals([], $session->keys());
  }

  #[Test]
  public function key() {
    $session= $this->fixture()->create();
    $session->register('name', 'value');
    Assert::equals(['name'], $session->keys());
  }

  #[Test]
  public function keys() {
    $session= $this->fixture()->create();
    $session->register('name1', 'value1');
    $session->register('name2', 'value2');
    Assert::equals(['name1', 'name2'], $session->keys());
  }

  #[Test, Expect(IllegalArgumentException::class), Values(['', 'too-short'])]
  public function raises_error_when_key_is_not_long_enough($key) {
    new CookieBased(new Secret($key));
  }

  #[Test]
  public function id_changes_when_registering_values() {
    $session= $this->fixture()->create();
    $id= $session->id();
    $session->register('key', 'value');

    Assert::notEquals($id, $session->id());
  }

  #[Test]
  public function id_changes_when_removing_registered_values() {
    $session= $this->fixture()->create();
    $id= $session->id();
    $session->register('key', 'value');
    $session->remove('key');

    Assert::notEquals($id, $session->id());
  }
}