<?php

namespace App\Support\Discovery;

/**
 * NaviSuite LAN discovery, version 1. One UDP datagram each way.
 *
 * Question (client to UDP port 47810, broadcast or unicast):
 *   {"type":"navisuite.discover","v":1,"nonce":"<16-64 hex>","vessel":12}
 *   "vessel" is optional: a client looking for one vessel's server sets it,
 *   and servers of other vessels stay silent.
 *
 * Answer (server to the sender's address and port, unicast):
 *   {"type":"navisuite.server","v":1,"nonce":"<the same>","app":"NaviSuite",
 *    "vessel":{"id":12,"name":"MV Example"},"host":"master-pc",
 *    "https_port":443,"http_port":80,"ca_sha256":"AB:CD:...","release":"..."}
 *
 * See docs/checkpoint-4-desktop-client.md for the full description and its
 * security implications.
 */
final class DiscoveryProtocol
{
    public const VERSION = 1;

    public const REQUEST_TYPE = 'navisuite.discover';

    public const REPLY_TYPE = 'navisuite.server';

    /** Larger datagrams are ignored unread. */
    public const MAX_REQUEST_BYTES = 512;

    /**
     * @return array{nonce: string, vessel: ?int}|null null when the datagram
     *         is not a valid discovery question
     */
    public static function parseRequest(string $payload): ?array
    {
        if ($payload === '' || strlen($payload) > self::MAX_REQUEST_BYTES) {
            return null;
        }

        $data = json_decode($payload, true, 4);
        if (! is_array($data) || array_is_list($data)) {
            return null;
        }

        if (($data['type'] ?? null) !== self::REQUEST_TYPE) {
            return null;
        }

        // Newer clients may speak a later version; they still get a v1 answer.
        if (! is_int($data['v'] ?? null) || $data['v'] < 1) {
            return null;
        }

        $nonce = $data['nonce'] ?? null;
        if (! is_string($nonce) || ! preg_match('/^[0-9a-f]{16,64}$/', $nonce)) {
            return null;
        }

        $vessel = $data['vessel'] ?? null;
        if ($vessel !== null && (! is_int($vessel) || $vessel < 1)) {
            return null;
        }

        return ['nonce' => $nonce, 'vessel' => $vessel];
    }

    /**
     * @param  array{vessel_id: int, vessel_name: ?string, host: string, https_port: int, http_port: int, ca_sha256: ?string, release: ?string}  $info
     */
    public static function encodeReply(string $nonce, array $info): string
    {
        return json_encode([
            'type' => self::REPLY_TYPE,
            'v' => self::VERSION,
            'nonce' => $nonce,
            'app' => 'NaviSuite',
            'vessel' => [
                'id' => $info['vessel_id'],
                'name' => $info['vessel_name'] === null ? null : mb_substr($info['vessel_name'], 0, 100),
            ],
            'host' => $info['host'],
            'https_port' => $info['https_port'],
            'http_port' => $info['http_port'],
            'ca_sha256' => $info['ca_sha256'],
            'release' => $info['release'] === null ? null : mb_substr($info['release'], 0, 64),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** "AB:CD:..." from a PEM certificate, or null if it cannot be read. */
    public static function certificateFingerprint(string $pem): ?string
    {
        $hex = @openssl_x509_fingerprint($pem, 'sha256');

        return $hex === false ? null : implode(':', str_split(strtoupper($hex), 2));
    }
}
