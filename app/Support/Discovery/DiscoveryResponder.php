<?php

namespace App\Support\Discovery;

use Closure;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Decides whether and how to answer one discovery datagram.
 *
 * Kept free of sockets so it can be tested directly; the
 * navisuite:discovery command does the network part.
 */
class DiscoveryResponder
{
    /**
     * Only senders on a private LAN are answered. The responder never talks
     * to the internet, even if a port were forwarded to it by mistake.
     */
    public const ALLOWED_PEERS = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', '127.0.0.0/8'];

    /** @var array<string, list<int>> sender address => answer times */
    private array $perPeer = [];

    /** @var array<int, int> second => answers in that second */
    private array $perSecond = [];

    private ?array $info = null;

    private int $infoLoadedAt = 0;

    public int $answered = 0;

    public int $ignored = 0;

    public int $limited = 0;

    /**
     * @param  Closure(): array  $loadInfo  builds the server description
     * @param  Closure(): int|null  $clock  current time in seconds, for tests
     */
    public function __construct(
        private Closure $loadInfo,
        private int $maxPerPeer = 5,
        private int $maxPerSecond = 20,
        private ?Closure $clock = null,
        private int $infoTtl = 60,
    ) {
    }

    /** The answer to send back to $peerIp, or null to stay silent. */
    public function handle(string $payload, string $peerIp): ?string
    {
        if (! filter_var($peerIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! IpUtils::checkIp($peerIp, self::ALLOWED_PEERS)) {
            $this->ignored++;

            return null;
        }

        $request = DiscoveryProtocol::parseRequest($payload);
        if ($request === null) {
            $this->ignored++;

            return null;
        }

        $info = $this->info();
        if ($request['vessel'] !== null && $request['vessel'] !== $info['vessel_id']) {
            $this->ignored++;

            return null;
        }

        if (! $this->allow($peerIp)) {
            $this->limited++;

            return null;
        }

        $this->answered++;

        return DiscoveryProtocol::encodeReply($request['nonce'], $info);
    }

    private function info(): array
    {
        $now = $this->now();
        if ($this->info === null || $now - $this->infoLoadedAt >= $this->infoTtl) {
            $this->info = ($this->loadInfo)();
            $this->infoLoadedAt = $now;
        }

        return $this->info;
    }

    private function allow(string $peer): bool
    {
        $now = $this->now();

        foreach (array_keys($this->perSecond) as $second) {
            if ($second < $now) {
                unset($this->perSecond[$second]);
            }
        }
        if (($this->perSecond[$now] ?? 0) >= $this->maxPerSecond) {
            return false;
        }

        $recent = array_values(array_filter($this->perPeer[$peer] ?? [], fn (int $t) => $t > $now - 10));
        if (count($recent) >= $this->maxPerPeer) {
            $this->perPeer[$peer] = $recent;

            return false;
        }

        $recent[] = $now;
        $this->perPeer[$peer] = $recent;
        $this->perSecond[$now] = ($this->perSecond[$now] ?? 0) + 1;

        // Forget senders that went quiet, so the table cannot grow forever.
        if (count($this->perPeer) > 1024) {
            $this->perPeer = array_filter($this->perPeer, fn (array $times) => max($times) > $now - 10);
        }

        return true;
    }

    private function now(): int
    {
        return $this->clock ? ($this->clock)() : time();
    }
}
