<?php namespace web\session\unittest;

use lang\FormatException;
use test\{Assert, Expect, Test, Values};
use util\Secret;
use web\session\cookie\Encryption;

class EncryptionTest {

  /** @return iterable */
  private function available() {
    foreach (Encryption::available(new Secret('tlw3/ELaLfu3kmpzQJ0MDCdRG2b8Le+X')) as $format) {
      yield [$format];
    }
  }

  #[Test, Values(from: 'available')]
  public function empty_roundtrip($impl) {
    Assert::equals('', $impl->decrypt($impl->encrypt('')));
  }

  #[Test, Values(from: 'available')]
  public function test_roundtrip($impl) {
    Assert::equals('Test', $impl->decrypt($impl->encrypt('Test')));
  }

  #[Test, Values(from: 'available')]
  public function roundtrip_with_64_kbytes($impl) {
    $payload= str_repeat('A*', 32768);
    Assert::equals($payload, $impl->decrypt($impl->encrypt($payload)));
  }

  #[Test, Expect(FormatException::class), Values(from: 'available')]
  public function detects_payload_being_tampered_with($impl) {
    $encrypted= $impl->encrypt('Test');
    $encrypted[0]= chr(ord($encrypted[0]) + 1);

    $impl->decrypt($encrypted);
  }

  #[Test, Expect(FormatException::class), Values(from: 'available')]
  public function detects_payload_being_shortened($impl) {
    $encrypted= $impl->encrypt('Test');
    $encrypted= substr($encrypted, 0,  -1);

    $impl->decrypt($encrypted);
  }

  #[Test, Expect(FormatException::class), Values(from: 'available')]
  public function detects_payload_being_appended_to($impl) {
    $encrypted= $impl->encrypt('Test');
    $encrypted.= "\0";

    $impl->decrypt($encrypted);
  }
}