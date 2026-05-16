<?php

namespace Tests\Unit;

use App\Services\Channels\DTO\MediaPayload;
use App\Services\Channels\WhatsAppDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class WhatsAppInteractiveTest extends TestCase
{
    public function test_sends_a_three_button_interactive_message_with_image_header(): void
    {
        $sent = [];
        $mock = new MockHandler([new Response(200, [], json_encode(['messages' => [['id' => 'wamid.ok']]]))]);
        $stack = HandlerStack::create($mock);
        $stack->push(function (callable $h) use (&$sent) {
            return function (Request $req, array $opts) use ($h, &$sent) {
                $sent[] = ['method' => $req->getMethod(), 'uri' => (string) $req->getUri(), 'body' => json_decode((string) $req->getBody(), true)];

                return $h($req, $opts);
            };
        });
        $http = new Client(['handler' => $stack, 'http_errors' => false]);

        $driver = new WhatsAppDriver([
            'graph_base' => 'https://graph.facebook.com',
            'graph_version' => 'v20.0',
            'phone_number_id' => '999',
            'access_token' => 'tok',
        ]);
        // Inject our mocked Guzzle.
        $rc = new \ReflectionClass($driver);
        $p = $rc->getProperty('http');
        $p->setValue($driver, $http);

        $r = $driver->sendInteractiveButtons(
            '99559900',
            'რომელია უკეთესი?',
            [
                ['id' => 'buy_X1',    'title' => 'შეკვეთა'],
                ['id' => 'alts_X1',   'title' => 'სხვა ვარიანტი'],
                ['id' => 'pickup_X1', 'title' => 'ფილიალში'],
            ],
            new MediaPayload('image', 'https://img.example/p.jpg'),
            'საწყობში: 12 ცალი',
        );

        $this->assertTrue($r->ok);
        $this->assertSame('wamid.ok', $r->platformMsgId);

        $body = $sent[0]['body'];
        $this->assertSame('interactive', $body['type']);
        $this->assertSame('button', $body['interactive']['type']);
        $this->assertCount(3, $body['interactive']['action']['buttons']);
        $this->assertSame('buy_X1', $body['interactive']['action']['buttons'][0]['reply']['id']);
        $this->assertSame('image', $body['interactive']['header']['type']);
        $this->assertSame('საწყობში: 12 ცალი', $body['interactive']['footer']['text']);
    }

    public function test_truncates_titles_to_whatsapp_20_char_limit(): void
    {
        $sent = [];
        $mock = new MockHandler([new Response(200, [], json_encode(['messages' => [['id' => 'x']]]))]);
        $stack = HandlerStack::create($mock);
        $stack->push(function ($h) use (&$sent) {
            return function ($req, $opts) use ($h, &$sent) {
                $sent[] = json_decode((string) $req->getBody(), true);

                return $h($req, $opts);
            };
        });
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        $driver = new WhatsAppDriver([
            'graph_base' => 'https://graph.facebook.com', 'graph_version' => 'v20.0',
            'phone_number_id' => '1', 'access_token' => 't',
        ]);
        $rc = new \ReflectionClass($driver);
        $p = $rc->getProperty('http');
        $p->setValue($driver, $http);

        $driver->sendInteractiveButtons('1', 'x', [
            ['id' => 'i', 'title' => str_repeat('ა', 40)],
        ]);

        $title = $sent[0]['interactive']['action']['buttons'][0]['reply']['title'];
        $this->assertLessThanOrEqual(20, mb_strlen($title));
    }
}
