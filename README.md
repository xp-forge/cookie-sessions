Sessions for the XP Framework
========================================================================

[![Build status on GitHub](https://github.com/xp-forge/cookie-sessions/workflows/Tests/badge.svg)](https://github.com/xp-forge/cookie-sessions/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/cookie-sessions/version.png)](https://packagist.org/packages/xp-forge/sessions)

Cookie-based sessions require no serverside storage and thus scale very well.

Usage
-----

Inside the routing setup:

```php
use web\session\CookieBased;
use web\auth\SessionBased;
use util\Secret;

$secret= new Secret('y+lCLaMzxlnHjkTt3FoPVQ_x5XTHSr78'); // 32 bytes!
$sessions= new CookieBased($secret);

$auth= new SessionBased($flow, $sessions);
return $auth->required(function($req, $res) {
  // Use $req->value('user')
});
```

A binary-safe 32 byte secret key can be generated using the following:

```bash
$ xp -d 'base64_encode(random_bytes(24))'
string(32) "ai4BO6rpwgezJztTalg5rt29XNJwMRMQ"
```

Security
--------
As stated [here](https://github.com/SaintFlipper/EncryptedSession#why-use-server-side-session-storage-instead-):

> [The] security risk of putting the session data in the session cookie is the danger of "session replay" attacks. If a valid session cookie is captured from a user's browser (it's visible in the browser's developer console) then that cookie can be copied to another machine and used in a rogue session at any time.

Though the same applies for server-side sessions with session IDs transmitted via cookies, we can destroy the attached session on the server-side to invalidate in these cases, e.g. by deleting the session file or removing the relevant row from the database. For cookie-based sessions, there is no way to remotely guarantee session destruction - and thus no way for a safe user-based "Log me off on all devices" functionality.

However, if we use cookie-based sessions to store short-lived access tokens, we can reduce this risk significantly: A replay can only occur during that window of time. For Microsoft 365, this time is roughly one hour.

👉 **Long story short**: If there's an easy possibility to use server-side sessions, do that. If dependencies come at a high cost and you have ways of managing the risk, or for development purposes, this implementation can be a valid choice.

Internals
---------
The session data is encrypted in the cookie and then encoded in base64 to use 7 bit only. The first byte controls the algorithm used:

* `S` for Sodium, using [sodium_crypto_box_open()](https://www.php.net/sodium_crypto_box_open), requires Sodium extension
* `O` for OpenSSL, using [openssl_encrypt()](https://www.php.net/openssl_encrypt), requires OpenSSL extension

The encrypted value is signed by a hash to detect any [bit flipping attacks](https://en.wikipedia.org/wiki/Bit-flipping_attack).

Compression
-----------
To prevent hitting the [browser cookie limits](http://browsercookielimits.iain.guru/) too early, the cookie values are compressed using LZW (*which is [relatively easy to implement](http://www.rosettacode.org/wiki/LZW_compression#Simpler_Version) and gives good savings without requiring an extra PHP extension compiled in*) if it's deemed worthwhile. If the cookie value is compressed, the indicators above appear in lowercase (`s` and `o` instead of `S` and `O`).

An example:

* JSON value (response from `https://api.twitter.com/1.1/account/verify_credentials.json`): **2814 bytes**
* Encrypted and encoded cookie value: **3807 bytes** (*pretty close to the limit!*)
* If compressed, decreases to **2477 bytes** (*more than a kilobyte saved, 65% of the size*)

See also
--------
https://github.com/SaintFlipper/EncryptedSession
https://blog.miguelgrinberg.com/post/how-secure-is-the-flask-user-session