<?php
class GoogleAuthenticator {
    protected $_codeLength = 6;

    public function createSecret($secretLength = 16) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $secretLength; $i++) {
            $secret .= $validChars[rand(0, 31)];
        }
        return $secret;
    }

    public function getQRCodeGoogleUrl($name, $secret, $title = null) {
        $urlencoded = urlencode('otpauth://totp/'.$name.'?secret='.$secret.'');
        if (isset($title)) {
            $urlencoded .= urlencode('&issuer='.urlencode($title));
        }
        return 'https://api.qrserver.com/v1/create-qr-code/?data='.$urlencoded.'&size=200x200';
    }

    public function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode == $code) {
                return true;
            }
        }
        return false;
    }

    public function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) { $timeSlice = floor(time() / 30); }
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsArray = str_split($base32chars);
        $base32charsFlip = array_flip($base32charsArray);
        $secret = strtoupper($secret);
        $secretArray = str_split($secret);
        $binarySecret = "";
        foreach ($secretArray as $char) {
            $binarySecret .= str_pad(decbin($base32charsFlip[$char]), 5, "0", STR_PAD_LEFT);
        }
        $binarySecret = str_split($binarySecret, 8);
        $secret = "";
        foreach ($binarySecret as $bin) {
            $secret .= chr(bindec($bin));
        }
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secret, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hash = unpack('N', substr($hmac, $offset, 4));
        $hash = $hash[1] & 0x7FFFFFFF;
        $modulo = pow(10, $this->_codeLength);
        return str_pad($hash % $modulo, $this->_codeLength, '0', STR_PAD_LEFT);
    }
}
?>