<?php

namespace App\Services\Feeds;

use InvalidArgumentException;

class PublicUrl
{
    public function validate(string $url): string
    {
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || preg_match('/[\x00-\x20\\\\]/', $url)) {
            throw new InvalidArgumentException('URL HTTP(S) invalide, sans identifiants ni fragment attendue.');
        }
        $host = strtolower($parts['host'] ?? '');
        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/', $host)
            || preg_match('/\.(localhost|local|internal|test|invalid)$/', $host)
            || ! in_array($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80), [80, 443], true)) {
            throw new InvalidArgumentException('Le flux doit utiliser un domaine public et le port 80 ou 443.');
        }

        return $host;
    }

    public function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $bytes = array_map('intval', explode('.', $ip));

        return ! ($bytes[0] === 0 || $bytes[0] >= 224
            || ($bytes[0] === 100 && $bytes[1] >= 64 && $bytes[1] <= 127)
            || ($bytes[0] === 192 && $bytes[1] === 0)
            || ($bytes[0] === 198 && in_array($bytes[1], [18, 19], true)));
    }

    /** @return list<string> */
    public function resolve(string $host): array
    {
        $addresses = gethostbynamel($host);
        if (! $addresses) {
            throw new InvalidArgumentException('Domaine introuvable ou sans adresse IPv4 publique.');
        }
        foreach ($addresses as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new InvalidArgumentException('Le domaine résout vers une adresse réseau interdite.');
            }
        }

        return array_values(array_unique($addresses));
    }
}
