<?php

class JwtService {
    private static $secret = "SERVICECORESECRET2025";

    public static function gerarToken(array $dados) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($dados);

        $h = self::base64url_encode($header);
        $p = self::base64url_encode($payload);

        $signature = hash_hmac('sha256', "$h.$p", self::$secret, true);
        $s = self::base64url_encode($signature);

        return "$h.$p.$s";
    }

    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
