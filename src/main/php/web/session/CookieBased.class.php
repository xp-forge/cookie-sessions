<?php namespace web\session;

use lang\FormatException;
use util\Secret;
use web\Cookie;
use web\session\cookie\{Session, Encryption, Compression};

/**
 * Cookie-based sessions. The session data is encrypted in the cookie and
 * then encoded in base64 to use 7 bit only. The first byte controls the
 * algorithm used:
 *
 * - `s` for Sodium, using sodium_crypto_box_open()
 * - `o` for OpenSSL, using openssl_encrypt()
 *
 * The encrypted value is signed by a hash to detect any bit flipping attacks.
 *
 * @see   https://github.com/SaintFlipper/EncryptedSession
 * @test  web.session.unittest.CookieBasedTest
 */
class CookieBased extends Sessions {
  private $encryption, $compression;

  /**
   * Creates an new cookie-based session
   *
   * @param  string|util.Secret|web.session.cookie.Encryption $arg
   * @throws lang.IllegalStateException
   */
  public function __construct($arg) {
    if ($arg instanceof Encryption) {
      $this->encryption= $arg;
    } else {
      $this->encryption= Encryption::using($arg instanceof Secret ? $arg : new Secret($arg));
    }
    $this->compression= new Compression();
  }

  /**
   * URL-safe base64 encoding
   *
   * @see    https://datatracker.ietf.org/doc/html/rfc4648#section-5
   * @param  string $input
   * @return string
   */
  private function encode($input) {
    return rtrim(strtr(base64_encode($input), '/+', '_-'), '=');
  }

  /**
   * URL-safe base64 decoding
   *
   * @param  string $input
   * @return string
   */
  private function decode($input) {
    return base64_decode(strtr($input, '_-', '/+').'===');
  }

  /**
   * Creates the serialized form
   * 
   * @param  [:var] $value
   * @param  int $expire
   * @return string
   */
  public function serialize($values, $expire) {
    $value= json_encode([$values, $expire]);

    // Indicate compressed using lowercase identifiers
    if ($this->compression->worthwhile(strlen($value))) {
      $id= strtolower($this->encryption->id());
      $value= $this->compression->compress($value);
    } else {
      $id= $this->encryption->id();
    }

    return $id.$this->encode($this->encryption->encrypt($value));
  }

  /**
   * Creates a session
   *
   * @return web.session.Session
   */
  public function create() {
    $now= time();
    return new Session($this, [], $now + $this->duration);
  }

  /**
   * Opens an existing and valid session. 
   *
   * @param  string $token
   * @return ?web.session.ISession
   */
  public function open($token) {

    // The first byte is an identifier indicating the encryption algorithm used. If
    // this identifier doesn't match the one in use, regard the session as invalid.
    if (0 !== strncasecmp($this->encryption->id(), $token, 1)) return null;

    // If the ciphertext has been tampered with, the encryption implementation will
    // raise an exception - handle this silently like an invalid session.
    try {
      $plain= $this->encryption->decrypt($this->decode(substr($token, 1)));
    } catch (FormatException $e) {
      return null;
    }

    // The identifier in lowercase indicates compressed data, see serialize()
    if ($token[0] >= 'a') {
      $serialized= json_decode($this->compression->decompress($plain), true);
    } else {
      $serialized= json_decode($plain, true);
    }

    // Check JSON is valid, then check expiry time
    if (null === $serialized || time() > $serialized[1]) return null;

    return new Session($this, ...$serialized);
  }
}