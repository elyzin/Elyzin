<?php
/**
 * https://github.com/defuse/password-hashing
 * https://github.com/o2ps/TotpAuthenticator
 */
namespace Elyzin\Service;

class InvalidHashException extends \Exception
{}
class CannotPerformOperationException extends \Exception
{}

use ParagonIE\ConstantTime\Base32;
use \Elyzin\Controller\App;

class Security
{
    // Default values, override with user settings
    private static $conf = [
        'site' => '',
        'algorithm' => 'sha256',
        'iteration' => 64000,
        'size' => 18,
        'salt' => 24,
        'rehash' => false,
        'totpWindow' => 1,
    ];

    // Hash index sequence, NOT MEANT TO BE CHANGED ONCE IN ACTION
    private static $index = ['algorithm', 'iteration', 'size', 'salt', 'pbkdf2', 'stamp'];

    public static function conf($conf = array())
    {
        self::$conf = array_merge(self::$conf, $conf);
    }
    
    public static function getCsrfToken()
    {
        $_SESSION['post_key'] = bin2hex(session_id() . \random_bytes(16));        
        return hash_hmac('sha256', trim(App::$request, '/'), $_SESSION['post_key']);
    }
    
    public static function checkCsrfToken(string $token)
    {
        if(isset($_SESSION['post_key']) && !empty($token)){
            $post_key = $_SESSION['post_key'];
            unset($_SESSION['post_key']); // Ensure single usage
            return hash_equals(hash_hmac('sha256', trim(App::$request, '/'), $post_key), $token);
        }
        return false;
    }
    /**
     * Hash a password with PBKDF2
     *
     * @param string $password
     * @return string
     */
    public static function makePass($password)
    {
        // format: algorithm:iterations:outputSize:salt:pbkdf2output
        if (!\is_string($password)) {
            throw new InvalidArgumentException(
                "make(): Expected a string"
            );
        }
        if (\function_exists('random_bytes')) {
            try {
                $salt_raw = \random_bytes(self::$conf['salt']);
            } catch (Error $e) {
                $salt_raw = false;
            } catch (Exception $e) {
                $salt_raw = false;
            } catch (TypeError $e) {
                $salt_raw = false;
            }
        } else {
            $salt_raw = @\mcrypt_create_iv(self::$conf['salt'], MCRYPT_DEV_URANDOM);
        }
        if ($salt_raw === false) {
            throw new CannotPerformOperationException(
                "Random number generator failed. Not safe to proceed."
            );
        }

        $hash['stamp'] = \time();
        $hash['algorithm'] = self::$conf['algorithm'];
        $hash['iteration'] = self::$conf['iteration'];
        $hash['size'] = self::$conf['size'];
        $hash['salt'] = \base64_encode($salt_raw);
        $hash['pbkdf2'] = \base64_encode(self::pbkdf2(self::$conf['algorithm'], $password, $salt_raw, self::$conf['iteration'], self::$conf['size'], true));

        return implode(":", array_merge(array_flip(self::$index), $hash));
    }

    /**
     * Verify that a password matches the stored hash
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function checkPass($password, $hash)
    {
        if (!\is_string($password) || !\is_string($hash)) {
            throw new InvalidArgumentException(
                "check(): Expected two strings"
            );
        }
        $params = \explode(":", $hash);
        if (\count($params) !== count(self::$index)) {
            throw new InvalidHashException(
                "Fields are missing from the password hash."
            );
        }

        $pbkdf2 = \base64_decode($params[array_search('pbkdf2', self::$index)], true);
        if ($pbkdf2 === false) {
            throw new InvalidHashException(
                "Base64 decoding of pbkdf2 output failed."
            );
        }

        $salt_raw = \base64_decode($params[array_search('salt', self::$index)], true);
        if ($salt_raw === false) {
            throw new InvalidHashException(
                "Base64 decoding of salt failed."
            );
        }

        $storedOutputSize = (int) $params[array_search('size', self::$index)];
        if (Helper::strlen($pbkdf2) !== $storedOutputSize) {
            throw new InvalidHashException(
                "PBKDF2 output length doesn't match stored output length."
            );
        }

        $iterations = (int) $params[array_search('iteration', self::$index)];
        if ($iterations < 1) {
            throw new InvalidHashException(
                "Invalid number of iterations. Must be >= 1."
            );
        }

        return self::slow_equals(
            $pbkdf2,
            self::pbkdf2(
                $params[array_search('algorithm', self::$index)],
                $password,
                $salt_raw,
                $iterations,
                Helper::strlen($pbkdf2),
                true
            )
        );
    }

    /**
     * Checks if rehash is required
     *
     * @param string $pass
     * @param string $hash
     * @return string|bool
     */
    public static function rehash($password, $hash)
    {
        if (!\is_string($password) || !\is_string($hash)) {
            throw new InvalidArgumentException(
                "rehash(): Expected two strings"
            );
        }

        if (!self::$conf['rehash']) {
            return false;
        }

        $rehash = 0;
        $params = \explode(":", $hash);
        foreach (['algorithm', 'iteration', 'size', 'salt'] as $key) {
            if ($params[array_search($key, self::$index)] !== self::$conf[$key]) {
                $rehash++;
            }
        }

        if (empty($rehash)) {
            return false;
        }
        return self::make($password);
    }

    public static function getNewSecret(): string
    {
        return Base32::encodeUpper(random_bytes(20));
    }

    public static function getTotpUri(string $user, string $secret): string
    {
        return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=otpauth://totp/{$user}@"
        . self::$conf['site'] . "?secret={$secret}&issuer=" . self::$conf['site'];
    }

    public static function checkTotp($code, string $secret): bool
    {
        for ($offset = -self::$conf['totpWindow']; $offset <= self::$conf['totpWindow']; $offset++) {
            if ((int) $code === self::getOneTimePassword($secret, self::getTimestamp($offset))) {
                return true;
            }
        }
        return false;
    }

    private static function getOneTimePassword(string $secret, string $timestamp): int
    {
        if (!preg_match('/^[A-Z2-7]+$/', $secret)) {
            throw new \Exception("Seed contains invalid characters. Make sure it is a valid uppercase base32 string.");
        }

        if (strlen($secret) < 16) {
            throw new \Exception("Seed is too short. It must be at least 16 base32 digits long.");
        }

        $hash = hash_hmac('sha1', $timestamp, Base32::decodeUpper($secret), true);
        $offset = ord($hash[19]) & 0xF;

        return (
            ((ord($hash[$offset + 0]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            ((ord($hash[$offset + 3]) & 0xFF))
        ) % 1e6;
    }

    private static function getTimestamp(int $offset): string
    {
        return pack('N*', 0) . pack('N*', floor((SCRIPT_START + ($offset * 30)) / 30));
    }

    /**
     * Compares two strings $a and $b in length-constant time.
     *
     * @param string $a
     * @param string $b
     * @return bool
     */
    public static function slow_equals($a, $b)
    {
        if (!\is_string($a) || !\is_string($b)) {
            throw new InvalidArgumentException(
                "slow_equals(): expected two strings"
            );
        }
        if (\function_exists('hash_equals')) {
            return \hash_equals($a, $b);
        }

        // PHP < 5.6 polyfill:
        $diff = Helper::strlen($a) ^ Helper::strlen($b);
        for ($i = 0; $i < Helper::strlen($a) && $i < Helper::strlen($b); $i++) {
            $diff |= \ord($a[$i]) ^ \ord($b[$i]);
        }
        return $diff === 0;
    }

    /*
     * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
     * $algorithm - The hash algorithm to use. Recommended: SHA256
     * $password - The password.
     * $salt - A salt that is unique to the password.
     * $count - Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * $key_length - The length of the derived key in bytes.
     * $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
     * Returns: A $key_length-byte key derived from the password and salt.
     *
     * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
     *
     * This implementation of PBKDF2 was originally created by https://defuse.ca
     * With improvements by http://www.variations-of-shadow.com
     */
    public static function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
    {
        // Type checks:
        if (!\is_string($algorithm)) {
            throw new InvalidArgumentException(
                "pbkdf2(): algorithm must be a string"
            );
        }
        if (!\is_string($password)) {
            throw new InvalidArgumentException(
                "pbkdf2(): password must be a string"
            );
        }
        if (!\is_string($salt)) {
            throw new InvalidArgumentException(
                "pbkdf2(): salt must be a string"
            );
        }
        // Coerce strings to integers with no information loss or overflow
        $count += 0;
        $key_length += 0;

        $algorithm = \strtolower($algorithm);
        if (!\in_array($algorithm, \hash_algos(), true)) {
            throw new CannotPerformOperationException(
                "Invalid or unsupported hash algorithm."
            );
        }

        // Whitelist, or we could end up with people using CRC32.
        $ok_algorithms = array(
            "sha1", "sha224", "sha256", "sha384", "sha512",
            "ripemd160", "ripemd256", "ripemd320", "whirlpool",
        );
        if (!\in_array($algorithm, $ok_algorithms, true)) {
            throw new CannotPerformOperationException(
                "Algorithm is not a secure cryptographic hash function."
            );
        }

        if ($count <= 0 || $key_length <= 0) {
            throw new CannotPerformOperationException(
                "Invalid PBKDF2 parameters."
            );
        }

        if (\function_exists("hash_pbkdf2")) {
            // The output length is in NIBBLES (4-bits) if $raw_output is false!
            if (!$raw_output) {
                $key_length = $key_length * 2;
            }
            return \hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output);
        }

        $hash_length = Helper::strlen(\hash($algorithm, "", true));
        $block_count = \ceil($key_length / $hash_length);

        $output = "";
        for ($i = 1; $i <= $block_count; $i++) {
            // $i encoded as 4 bytes, big endian.
            $last = $salt . \pack("N", $i);
            // first iteration
            $last = $xorsum = \hash_hmac($algorithm, $last, $password, true);
            // perform the other $count - 1 iterations
            for ($j = 1; $j < $count; $j++) {
                $xorsum ^= ($last = \hash_hmac($algorithm, $last, $password, true));
            }
            $output .= $xorsum;
        }

        if ($raw_output) {
            return Helper::substr($output, 0, $key_length);
        } else {
            return \bin2hex(Helper::substr($output, 0, $key_length));
        }
    }

    /*
 * We need these strlen() and substr() functions because when
 * 'mbstring.func_overload' is set in php.ini, the standard strlen() and
 * substr() are replaced by mb_strlen() and mb_substr().
 */
}
