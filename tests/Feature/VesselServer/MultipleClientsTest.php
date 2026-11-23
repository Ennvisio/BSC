<?php

namespace Tests\Feature\VesselServer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AppMode\BuildsAppModeFixtures;
use Tests\TestCase;

/**
 * Several desktop clients on one vessel server at the same time, each with
 * a different crew member signed in (Checkpoint 4).
 *
 * Every request here is sent as its own browser would send it: only with that
 * client's session cookie. Nothing is shared in memory between requests, and
 * sessions are stored in files as on the master PC.
 */
class MultipleClientsTest extends TestCase
{
    use BuildsAppModeFixtures;

    private string $sessionDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAppModeFixtures();
        $this->useMode('vessel', $this->vesselId);

        $this->sessionDir = sys_get_temp_dir().'/navisuite-sessions-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->sessionDir);
        config(['session.driver' => 'file', 'session.files' => $this->sessionDir]);

        Route::middleware('web')->get('/__whoami', fn () => response()->json(['user' => auth()->id()]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessionDir);
        parent::tearDown();
    }

    public function test_each_client_keeps_its_own_crew_member_signed_in(): void
    {
        $master = $this->makeUser('master', $this->vesselId);
        $chief = $this->makeUser('chief-engineer', $this->vesselId);
        $officer = $this->makeUser('second-engineer', $this->vesselId);

        $bridge = $this->signIn($master);
        $engine = $this->signIn($chief);
        $office = $this->signIn($officer);

        $this->assertNotSame($bridge, $engine);
        $this->assertCount(3, File::files($this->sessionDir), 'one session file per client');

        // Interleaved requests from the three PCs.
        foreach ([[$bridge, $master], [$engine, $chief], [$office, $officer], [$bridge, $master], [$engine, $chief]] as [$cookie, $user]) {
            $this->as($cookie)->getJson('/__whoami')->assertExactJson(['user' => $user->id]);
        }
        $this->as(null)->getJson('/__whoami')->assertExactJson(['user' => null]);
    }

    public function test_signing_out_on_one_client_does_not_affect_the_others(): void
    {
        $master = $this->makeUser('master', $this->vesselId);
        $chief = $this->makeUser('chief-engineer', $this->vesselId);
        $bridge = $this->signIn($master);
        $engine = $this->signIn($chief);

        $this->as($bridge)->post('/logout')->assertRedirect();

        $this->as($bridge)->getJson('/__whoami')->assertExactJson(['user' => null]);
        $this->as($engine)->getJson('/__whoami')->assertExactJson(['user' => $chief->id]);
    }

    public function test_one_person_may_use_two_clients(): void
    {
        $master = $this->makeUser('master', $this->vesselId);
        $bridge = $this->signIn($master);
        $cabin = $this->signIn($master);

        $this->assertNotSame($bridge, $cabin);
        $this->as($bridge)->getJson('/__whoami')->assertExactJson(['user' => $master->id]);
        $this->as($cabin)->getJson('/__whoami')->assertExactJson(['user' => $master->id]);
    }

    public function test_a_client_cannot_sign_in_as_crew_of_another_vessel(): void
    {
        $other = $this->makeUser('master', $this->makeVessel('MT OTHER')->id);

        $this->freshClient();
        $response = $this->post('/login', ['email' => $other->email, 'password' => 'x']);
        $response->assertSessionHasErrors('email');
        $this->as($this->sessionCookie($response))->getJson('/__whoami')->assertExactJson(['user' => null]);
    }

    // ---------------------------------------------------------------- helpers

    /** Signs in from a new client and returns that client's session cookie. */
    private function signIn($user): string
    {
        $this->freshClient();
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'x']);
        $response->assertRedirect('/home');

        return $this->sessionCookie($response);
    }

    private function sessionCookie(TestResponse $response): string
    {
        $cookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie, 'the server sets a session cookie');

        return $cookie->getValue();
    }

    /** The next request comes from the client holding this cookie (or none). */
    private function as(?string $cookie): static
    {
        $this->freshClient();

        // withCredentials(): the test client sends cookies with JSON calls only then.
        return $cookie === null ? $this : $this->withCredentials()->withCookie(config('session.cookie'), $cookie);
    }

    /** Forgets everything held in memory, as a separate PC would have nothing. */
    private function freshClient(): void
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials = false;
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
