<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /**
     * Feature tests execute several HTTP requests in one PHP process, while
     * production resolves auth guards afresh for each request. Clear the
     * in-memory guard cache after every request so session/login/logout tests
     * exercise the real request lifecycle instead of reusing a stale guard.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        Auth::forgetGuards();

        return $response;
    }
}
