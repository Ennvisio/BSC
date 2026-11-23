<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * PD-04 (settled 2026-11-23): the home address sends a visitor to the
     * sign-in page (routes/web.php). The test used to expect Laravel's
     * sample welcome page.
     */
    public function testHomeSendsVisitorsToSignIn()
    {
        $this->get('/')->assertRedirect('/login');
    }
}
