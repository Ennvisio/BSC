<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use App\Support\Discovery\DiscoveryInfo;
use App\Support\Discovery\DiscoveryResponder;
use App\Support\Heartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Answers LAN discovery questions from the NaviSuite desktop clients.
 *
 * Runs for ever as the NaviSuite-Discovery Windows service on a vessel
 * server. It only reads: it never writes to the database and never sends
 * anything that was not asked for. See docs/checkpoint-4-desktop-client.md.
 */
class NaviSuiteDiscovery extends Command
{
    protected $signature = 'navisuite:discovery
        {--port= : UDP port (default: config navisuite.discovery.port, 47810)}
        {--bind= : Address to listen on (default 0.0.0.0, every network)}
        {--max-requests=0 : Stop after this many datagrams (testing)}
        {--max-seconds=0 : Stop after this many seconds (testing)}';

    protected $description = 'Answer NaviSuite desktop clients looking for this vessel server on the LAN';

    public function handle(AppMode $mode, DiscoveryInfo $info): int
    {
        if (! $mode->isVessel() || $mode->configurationErrors() !== []) {
            $this->error('Discovery runs on a correctly configured vessel server only (APP_MODE=vessel).');

            return self::FAILURE;
        }
        if (config('navisuite.discovery.enabled') === false || config('navisuite.discovery.enabled') === 'false') {
            $this->error('Discovery is switched off (APP_DISCOVERY_ENABLED=false).');

            return self::FAILURE;
        }

        $port = (int) ($this->option('port') ?: config('navisuite.discovery.port', 47810));
        $bind = (string) ($this->option('bind') ?: config('navisuite.discovery.bind', '0.0.0.0'));
        if ($port < 1 || $port > 65535 || ! filter_var($bind, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->error('Invalid --port or --bind.');

            return self::FAILURE;
        }

        $socket = @stream_socket_server("udp://{$bind}:{$port}", $errno, $errstr, STREAM_SERVER_BIND);
        if ($socket === false) {
            $this->error("Cannot listen on UDP {$bind}:{$port}: {$errstr} ({$errno}).");
            Log::error("navisuite:discovery cannot listen on UDP {$bind}:{$port}: {$errstr}");

            return self::FAILURE;
        }

        $responder = new DiscoveryResponder(
            loadInfo: fn () => $info(),
            maxPerPeer: (int) config('navisuite.discovery.max_replies_per_peer', 5),
            maxPerSecond: (int) config('navisuite.discovery.max_replies_per_second', 20),
        );

        $this->info("Answering NaviSuite discovery on UDP {$bind}:{$port}.");
        Log::info("navisuite:discovery listening on UDP {$bind}:{$port}");

        $maxRequests = (int) $this->option('max-requests');
        $maxSeconds = (int) $this->option('max-seconds');
        $started = time();
        $received = 0;
        $lastBeat = 0;
        $lastReport = time();

        while (true) {
            if (time() - $lastBeat >= 30) {
                Heartbeat::record(Heartbeat::DISCOVERY);
                $lastBeat = time();
            }

            $read = [$socket];
            $write = $except = null;
            if (@stream_select($read, $write, $except, 1) > 0) {
                // On Windows a datagram can fail with "connection reset" after
                // an earlier answer was refused; that is not an error here.
                $payload = @stream_socket_recvfrom($socket, 2048, 0, $peer);
                if (is_string($payload) && is_string($peer) && preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):(\d+)$/', $peer, $m)) {
                    $received++;
                    $reply = $responder->handle($payload, $m[1]);
                    if ($reply !== null) {
                        @stream_socket_sendto($socket, $reply, 0, $peer);
                    }
                }
            }

            if (time() - $lastReport >= 3600) {
                Log::info("navisuite:discovery last hour: {$responder->answered} answered, {$responder->ignored} ignored, {$responder->limited} rate-limited");
                $responder->answered = $responder->ignored = $responder->limited = 0;
                $lastReport = time();
            }

            if (($maxRequests > 0 && $received >= $maxRequests) || ($maxSeconds > 0 && time() - $started >= $maxSeconds)) {
                break;
            }
        }

        fclose($socket);

        return self::SUCCESS;
    }
}
