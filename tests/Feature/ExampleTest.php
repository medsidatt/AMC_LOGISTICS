<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The platform has no public landing page: unauthenticated visitors are
     * redirected into the login / Microsoft SSO flow rather than served a 200.
     */
    public function test_guests_are_redirected_from_the_root(): void
    {
        $this->get('/')->assertRedirect();
    }
}
