<?php

namespace Tests\Feature\VesselServer;

use App\Support\Discovery\DiscoveryInfo;
use App\Support\Discovery\DiscoveryProtocol;
use App\Support\Discovery\DiscoveryResponder;
use App\Support\Heartbeat;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/** LAN discovery: php artisan navisuite:discovery and its protocol. */
class DiscoveryTest extends TestCase
{
    use BuildsRequisitionSchema;

    private const NONCE = '0123456789abcdef0123456789abcdef';

    private array $info = [
        'vessel_id' => 12, 'vessel_name' => 'MV Example', 'host' => 'master-pc',
        'https_port' => 443, 'http_port' => 80, 'ca_sha256' => null, 'release' => '2026.11.20',
    ];

    private function request(array $overrides = []): string
    {
        return json_encode(array_merge(['type' => 'navisuite.discover', 'v' => 1, 'nonce' => self::NONCE], $overrides));
    }

    private function responder(?int &$now = null, int $perPeer = 5, int $perSecond = 20, ?int &$loads = null): DiscoveryResponder
    {
        $now ??= 1_000_000;
        $loads = 0;

        return new DiscoveryResponder(
            loadInfo: function () use (&$loads) { $loads++; return $this->info; },
            maxPerPeer: $perPeer,
            maxPerSecond: $perSecond,
            clock: function () use (&$now) { return $now; },
        );
    }

    // ------------------------------------------------------------------ protocol

    public function test_a_valid_question_is_understood(): void
    {
        $this->assertSame(['nonce' => self::NONCE, 'vessel' => null], DiscoveryProtocol::parseRequest($this->request()));
        $this->assertSame(['nonce' => self::NONCE, 'vessel' => 12], DiscoveryProtocol::parseRequest($this->request(['vessel' => 12])));
        $this->assertNotNull(DiscoveryProtocol::parseRequest($this->request(['v' => 2])), 'a later client version still gets an answer');
    }

    public function test_anything_else_is_ignored(): void
    {
        $bad = [
            '', 'hello', '[]', '{"type":"navisuite.discover"}', 'null',
            $this->request(['type' => 'other']),
            $this->request(['v' => 0]), $this->request(['v' => '1']),
            $this->request(['nonce' => 'short']), $this->request(['nonce' => 'ZZ23456789abcdef']),
            $this->request(['nonce' => str_repeat('a', 65)]),
            $this->request(['vessel' => '12']), $this->request(['vessel' => 0]),
            $this->request(['pad' => str_repeat('x', 600)]),
            str_repeat('{"a":', 20).'1'.str_repeat('}', 20),
        ];

        foreach ($bad as $payload) {
            $this->assertNull(DiscoveryProtocol::parseRequest($payload), "accepted: {$payload}");
        }
    }

    public function test_the_answer_echoes_the_nonce_and_describes_the_server(): void
    {
        $reply = json_decode(DiscoveryProtocol::encodeReply(self::NONCE, $this->info), true);

        $this->assertSame([
            'type' => 'navisuite.server', 'v' => 1, 'nonce' => self::NONCE, 'app' => 'NaviSuite',
            'vessel' => ['id' => 12, 'name' => 'MV Example'], 'host' => 'master-pc',
            'https_port' => 443, 'http_port' => 80, 'ca_sha256' => null, 'release' => '2026.11.20',
        ], $reply);
        $this->assertLessThan(512, strlen(DiscoveryProtocol::encodeReply(self::NONCE, ['vessel_name' => str_repeat('N', 500)] + $this->info)));
    }

    // ------------------------------------------------------------------ responder

    public function test_lan_senders_get_an_answer(): void
    {
        $responder = $this->responder();

        foreach (['192.168.1.20', '10.4.5.6', '172.20.0.9', '169.254.10.1', '127.0.0.1'] as $peer) {
            $reply = $responder->handle($this->request(), $peer);
            $this->assertNotNull($reply, $peer);
            $this->assertSame(self::NONCE, json_decode($reply, true)['nonce']);
        }
    }

    public function test_senders_outside_private_networks_are_never_answered(): void
    {
        $responder = $this->responder();

        foreach (['8.8.8.8', '172.32.0.1', '100.64.0.1', '::1', 'not-an-ip'] as $peer) {
            $this->assertNull($responder->handle($this->request(), $peer), $peer);
        }
        $this->assertSame(5, $responder->ignored);
    }

    public function test_a_question_for_another_vessel_gets_no_answer(): void
    {
        $responder = $this->responder();

        $this->assertNull($responder->handle($this->request(['vessel' => 99]), '192.168.1.20'));
        $this->assertNotNull($responder->handle($this->request(['vessel' => 12]), '192.168.1.20'));
    }

    public function test_answers_are_rate_limited_per_sender_and_overall(): void
    {
        $now = 1_000_000;
        $responder = $this->responder($now, perPeer: 5, perSecond: 8);

        $answers = 0;
        for ($i = 0; $i < 7; $i++) {
            $answers += $responder->handle($this->request(), '192.168.1.20') === null ? 0 : 1;
        }
        $this->assertSame(5, $answers, 'five per sender per 10 seconds');

        $others = 0;
        for ($i = 1; $i <= 6; $i++) {
            $others += $responder->handle($this->request(), "192.168.1.{$i}") === null ? 0 : 1;
        }
        $this->assertSame(3, $others, 'eight per second in total');
        $this->assertSame(5, $responder->limited);

        $now += 11;
        $this->assertNotNull($responder->handle($this->request(), '192.168.1.20'), 'the window moves on');
    }

    public function test_the_server_description_is_reused_for_a_minute(): void
    {
        $now = 1_000_000;
        $responder = $this->responder($now, loads: $loads);

        $responder->handle($this->request(), '192.168.1.20');
        $responder->handle($this->request(), '192.168.1.21');
        $this->assertSame(1, $loads);

        $now += 60;
        $responder->handle($this->request(), '192.168.1.22');
        $this->assertSame(2, $loads);
    }

    // ------------------------------------------------------------------ server description

    public function test_the_description_comes_from_the_configuration_and_database(): void
    {
        $this->buildRequisitionSchema();
        DB::table('vessels')->where('id', $this->vesselId)->update(['name' => 'MV Discovery']);
        $ca = $this->makeCertificate();
        config([
            'navisuite.mode' => 'vessel', 'navisuite.vessel_id' => $this->vesselId, 'navisuite.release' => 'r42',
            'app.url' => 'https://Master-PC', 'navisuite.tls.ca_certificate' => $ca['file'],
        ]);

        $info = app(DiscoveryInfo::class)();

        $this->assertSame($this->vesselId, $info['vessel_id']);
        $this->assertSame('MV Discovery', $info['vessel_name']);
        $this->assertSame('master-pc', $info['host']);
        $this->assertSame(443, $info['https_port']);
        $this->assertSame('r42', $info['release']);
        $this->assertSame($ca['fingerprint'], $info['ca_sha256']);
        $this->assertMatchesRegularExpression('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $info['ca_sha256']);
    }

    public function test_a_missing_database_or_certificate_does_not_stop_the_answer(): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => 12, 'navisuite.tls.ca_certificate' => '/nonexistent/ca.crt']);

        $info = app(DiscoveryInfo::class)();

        $this->assertNull($info['vessel_name']); // no vessels table in this test
        $this->assertNull($info['ca_sha256']);
    }

    // ------------------------------------------------------------------ command

    public function test_the_command_refuses_to_run_on_the_cloud_or_when_switched_off(): void
    {
        $this->artisan('navisuite:discovery', ['--max-seconds' => 1])->assertFailed();

        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => 12, 'navisuite.discovery.enabled' => false]);
        $this->artisan('navisuite:discovery', ['--max-seconds' => 1])->assertFailed();
    }

    public function test_the_service_answers_over_udp_and_reports_a_heartbeat(): void
    {
        $ca = $this->makeCertificate();
        $port = random_int(40000, 59000);
        $process = new Process(
            [PHP_BINARY, 'artisan', 'navisuite:discovery', '--bind=127.0.0.1', "--port={$port}", '--max-seconds=20'],
            base_path(),
            [
                'APP_MODE' => 'vessel', 'APP_VESSEL_ID' => '12', 'APP_URL' => 'https://udp-master',
                'APP_TLS_CA_FILE' => $ca['file'], 'APP_RELEASE' => 'udp-test',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
                'CACHE_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            ],
        );
        $process->start();

        try {
            $client = stream_socket_client("udp://127.0.0.1:{$port}", $errno, $errstr);
            stream_set_timeout($client, 0, 300_000);
            $reply = null;
            for ($i = 0; $i < 40 && $reply === null; $i++) {
                fwrite($client, $this->request());
                $data = fread($client, 2048);
                if (is_string($data) && $data !== '') {
                    $reply = json_decode($data, true);
                }
                if (! $process->isRunning()) {
                    break;
                }
            }

            $this->assertIsArray($reply, 'no answer; command output: '.$process->getOutput().$process->getErrorOutput());
            $this->assertSame('navisuite.server', $reply['type']);
            $this->assertSame(self::NONCE, $reply['nonce']);
            $this->assertSame(12, $reply['vessel']['id']);
            $this->assertNull($reply['vessel']['name']);
            $this->assertSame('udp-master', $reply['host']);
            $this->assertSame('udp-test', $reply['release']);
            $this->assertSame($ca['fingerprint'], $reply['ca_sha256']);

            // Garbage and other vessels' questions get silence.
            fwrite($client, 'garbage');
            fwrite($client, $this->request(['vessel' => 99]));
            $this->assertSame('', (string) fread($client, 2048));
            fclose($client);
        } finally {
            $process->stop(1);
        }
    }

    public function test_the_heartbeat_key_is_separate_from_the_other_services(): void
    {
        Heartbeat::record(Heartbeat::DISCOVERY);

        $this->assertSame(0, Heartbeat::age(Heartbeat::DISCOVERY));
        $this->assertNull(Heartbeat::age(Heartbeat::SCHEDULER));
    }

    /** @return array{file: string, fingerprint: string} */
    private function makeCertificate(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'NaviSuite Test CA'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 30, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $pem);
        $file = tempnam(sys_get_temp_dir(), 'ca').'.crt';
        file_put_contents($file, $pem);

        return ['file' => $file, 'fingerprint' => implode(':', str_split(strtoupper(openssl_x509_fingerprint($pem, 'sha256')), 2))];
    }
}
