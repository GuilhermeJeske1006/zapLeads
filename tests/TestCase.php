<?php

namespace Tests;

use Anthropic\Client as AnthropicClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test reaches the real Claude API (the .env key would be billed): an unfaked call fails
        // at once, like a network error. Fake answers by binding another client, or mock AIService.
        $this->app->instance(AnthropicClient::class, new AnthropicClient(
            apiKey: 'test-key',
            requestOptions: ['transporter' => new GuzzleClient(['handler' => HandlerStack::create(new MockHandler())]), 'maxRetries' => 0],
        ));
    }
}
