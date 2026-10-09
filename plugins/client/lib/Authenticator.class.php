<?php

class Authenticator {
    protected $codeLength = 6;
    protected $algorithm = 'sha1';   // 'sha1', 'sha256', 'sha512'

    /**
     * @param int $codeLength 6-10
     * @param string $algorithm 'sha1', 'sha256', 'sha512'
     * @throws InvalidArgumentException
     */
    public function __construct($codeLength = 6, $algorithm = 'sha1') {
        $codeLength = (int) $codeLength;
        if ($codeLength < 6 || $codeLength > 10) {
            throw new InvalidArgumentException('Code length must be between 6 and 10');
        }
        $algorithm = strtolower($algorithm);
        if (!in_array($algorithm, array('sha1', 'sha256', 'sha512'), true)) {
            throw new InvalidArgumentException('Algorithm must be sha1, sha256 or sha512');
        }
        $this->codeLength = $codeLength;
        $this->algorithm = $algorithm;
    }

    /**
     * Create a new secret (cryptographically secure)
     * @param int $secretLength Length in bytes (minimum 10 bytes -> 80 bits, max 64 bytes -> 512 bits)
     * @return string Base32 encoded secret
     * @throws RuntimeException if no secure random source is available
     */
    public function createSecret($secretLength = 20) // 20 bytes = 160 bits, default for SHA1/SHA256
    {
        // RFC 6238 recommends at least 128 bits (16 bytes)
        $secretLength = (int) $secretLength;
        if ($secretLength < 10) {
            $secretLength = 10;
        }
        if ($secretLength > 64) {
            $secretLength = 64;
        }

        $bytes = $this->secureRandomBytes($secretLength);
        return $this->base32Encode($bytes);
    }

    /**
     * Get current TOTP code
     * @param string $secret Base32 secret
     * @param int|null $timeSlice (optional) custom time slice, for testing
     * @return string
     */
    public function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = $this->base32Decode($secret);
        // Pack time as 8-byte binary string (high 32-bit zero, low 32-bit timeSlice)
        $time = pack('N2', 0, $timeSlice);
        $hmac = hash_hmac($this->algorithm, $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;
        $modulo = pow(10, $this->codeLength);
        return str_pad($value % $modulo, $this->codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a TOTP code (with time drift allowance)
     * @param string $secret
     * @param string $code
     * @param int $discrepancy Allowed windows (each 30s) before/after current time
     * @param int|null $currentTimeSlice
     * @return bool
     */
    public function verifyCode($secret, $code, $discrepancy = 1, $currentTimeSlice = null) {
        if ($currentTimeSlice === null) {
            $currentTimeSlice = floor(time() / 30);
        }
        $code = trim($code);
        if (strlen($code) !== $this->codeLength || !ctype_digit($code)) {
            return false;
        }
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculated = $this->getCode($secret, $currentTimeSlice + $i);
            if ($this->timingSafeEquals($calculated, $code)) {
                return true;
            }
        }
        return false;
    }

    // -------- Internal helpers --------

    /**
     * Cryptographically secure random bytes
     * @throws RuntimeException
     */
    private function secureRandomBytes($count) {
        // PHP 7+: random_bytes is best
        if (function_exists('random_bytes')) {
            try {
                return random_bytes($count);
            } catch (Exception $e) {
                // fall through to OpenSSL
            }
        }
        // OpenSSL fallback (PHP 5.6+)
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($count, $strong);
            if ($strong && $bytes !== false) {
                return $bytes;
            }
        }
        // Last resort (should not happen on modern systems)
        throw new RuntimeException('No secure random source available');
    }

    /**
     * Base32 encode binary data
     */
    private function base32Encode($binary) {
        $chars = $this->getBase32Table();
        $binaryLen = strlen($binary);
        $buffer = '';
        $result = '';
        $bits = 0;
        $value = 0;
        for ($i = 0; $i < $binaryLen; $i++) {
            $value = ($value << 8) | ord($binary[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $buffer .= $chars[($value >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $buffer .= $chars[($value << (5 - $bits)) & 31];
        }
        // Pad to multiple of 8
        $padLen = (8 - (strlen($buffer) % 8)) % 8;
        return $buffer . str_repeat('=', $padLen);
    }

    /**
     * Base32 decode (improved error handling, still compatible)
     */
    private function base32Decode($secret) {
        if (empty($secret)) {
            return '';
        }
        $chars = $this->getBase32Table();
        $charsFlipped = array_flip($chars);
        $secret = rtrim($secret, '='); // remove padding
        $binary = '';
        $bits = 0;
        $value = 0;
        $secretLen = strlen($secret);
        for ($i = 0; $i < $secretLen; $i++) {
            $char = $secret[$i];
            if (!isset($charsFlipped[$char])) {
                return ''; // invalid char
            }
            $value = ($value << 5) | $charsFlipped[$char];
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $binary .= chr(($value >> $bits) & 0xFF);
            }
        }
        return $binary;
    }

    private function getBase32Table() {
        return array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
            'Y', 'Z', '2', '3', '4', '5', '6', '7'
        );
    }

    /**
     * Timing-safe string comparison
     */
    private function timingSafeEquals($a, $b) {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $result = 0;
        for ($i = 0, $len = strlen($a); $i < $len; $i++) {
            $result |= (ord($a[$i]) ^ ord($b[$i]));
        }
        return $result === 0;
    }
}