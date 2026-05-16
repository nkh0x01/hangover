<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebhookVerificationTest extends TestCase
{
    public function test_whatsapp_verify_challenge_returns_challenge_on_token_match(): void
    {
        config()->set('channels.whatsapp.verify_token', 'test-token');
        config()->set('channels.whatsapp.app_secret',  'unused-in-get');

        $res = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=test-token&hub_challenge=12345');

        $res->assertOk();
        $this->assertSame('12345', $res->getContent());
    }

    public function test_whatsapp_verify_returns_403_on_token_mismatch(): void
    {
        config()->set('channels.whatsapp.verify_token', 'real-token');
        $res = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345');
        $res->assertForbidden();
    }
}
