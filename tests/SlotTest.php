<?php
use \Notionslot\Slot;

class SlotTest extends \PHPUnit\Framework\TestCase
{
    private $conf = [
        'notion_token' => 'secret_**********************************',
        'notion_db_id' => '123456aa-bc12-1234-5678-0987654321aa',
        'site_domain' => 'foo.example',
        'site_name' => 'My Foo',
        'notify_to' => 'me@foo.example',
        'reply_to' => 'hello@foo.example',
        'mail_from' => 'noreply@foo.example',
    ];

    private $data = [
        'name' => 'linyows',
        'email' => 'linyows@foo.example',
        'message' => 'Yo!',
        'ip' => '192.168.10.1',
    ];

    public function tearDown(): void
    {
        \Mockery::close();
    }

    public function testConstructThrowsExceptionWhenAnyEmptyConfig()
    {
        $this->expectException('Notionslot\ConfigMissingError');
        $this->expectExceptionMessage('notion_token is required as notionslot config');
        new Slot([]);
    }

    public function testIsValidWhenDataIsCorrect()
    {
        $slot = new Slot($this->conf);
        $this->assertTrue($slot->setData($this->data)->isValid());
    }

    public function testIsValidWhenDataIsNotCorrect()
    {
        $slot = new Slot($this->conf);
        $data = [
            'name' => '',
            'email' => '',
            'message' => '',
        ];
        $this->assertFalse($slot->setData($data)->isValid());
        $errors = [
            'name is required',
            'email is required',
            'ip is required',
            'message is required',
        ];
        $this->assertEquals($errors, $slot->errors());
    }

    public function testSendHeaderCallsPhpHeader()
    {
        $wrapper = \Mockery::mock(\Notionslot\Wrapper::class);
        $slot = new Slot($this->conf, $wrapper);
        $headers = [
            'Strict-Transport-Security: max-age=31536000; includeSubdomains; preload',
            'X-Frame-Options: DENY',
            'Vary: Accept, Accept-Encoding, Accept, X-Requested-With',
            'Content-Security-Policy: default-src \'none\'',
            'Access-Control-Allow-Origin: https://foo.example',
            'Access-Control-Allow-Headers: Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, Accept-Encoding, X-Requested-With, User-Agent',
            'Access-Control-Allow-Methods: POST, OPTIONS',
            'Referrer-Policy: origin-when-cross-origin, strict-origin-when-cross-origin',
            'X-Content-Type-Options: nosniff',
            'Content-Type: application/json; charset=utf-8',
        ];
        foreach ($headers as $v) {
            $wrapper->shouldReceive('header')->with($v);
        }
        $this->assertNotNull($slot->sendHeader([]));
    }

    public function testSendHeaderCallsPhpExitWhenHttpRequestIsGet()
    {
        $wrapper = \Mockery::mock(\Notionslot\Wrapper::class);
        $slot = new Slot($this->conf, $wrapper);
        $wrapper->shouldReceive('header')->with('Location: https://foo.example');
        $wrapper->shouldReceive('exit')->with(1);
        $this->assertNotNull($slot->sendHeader(['REQUEST_METHOD' => 'GET']));
    }

    public function testNotifyCallsMbSendMail()
    {
        $wrapper = \Mockery::mock(\Notionslot\Wrapper::class);
        $slot = new Slot($this->conf, $wrapper);
        $slot->setData($this->data);
        $to = 'me@foo.example';
        $subject = 'Contact from foo.example';
        $body = <<<EOL
Contact from foo.example here:

Full name:
  linyows
Email address:
  linyows@foo.example
IP:
  192.168.10.1
Message:
  Yo!

--
My Foo
https://foo.example
EOL;
        $headers = [
            'From' => 'noreply@foo.example',
            'Reply-To' => 'hello@foo.example',
        ];
        $wrapper->shouldReceive('mb_send_mail')->with($to, $subject, $body, $headers);
        $this->assertNotNull($slot->notify());
    }

    public function testReplyCallsMbSendMail()
    {
        $wrapper = \Mockery::mock(\Notionslot\Wrapper::class);
        $slot = new Slot($this->conf, $wrapper);
        $slot->setData($this->data);
        $to = 'linyows@foo.example';
        $subject = 'Thanks for your message from foo.example';
        $body = <<<EOL
It has accepted a your message to foo.example.

Full name:
  linyows
Email address:
  linyows@foo.example
IP:
  192.168.10.1
Message:
  Yo!

--
My Foo
https://foo.example
EOL;
        $headers = [
            'From' => 'noreply@foo.example',
            'Reply-To' => 'hello@foo.example',
        ];
        $wrapper->shouldReceive('mb_send_mail')->with($to, $subject, $body, $headers);
        $this->assertNotNull($slot->reply());
    }

    public function testSaveCallsCurlMethods()
    {
        $wrapper = \Mockery::mock(\Notionslot\Wrapper::class)->makePartial();
        $slot = new Slot($this->conf, $wrapper);
        $slot->setData($this->data);

        $header = [
            'Authorization: Bearer secret_**********************************',
            'Content-Type: application/json',
            'Notion-Version: 2022-06-28',
        ];

        $body = [
            'parent' => [
                'type' => 'database_id',
                'database_id' => '123456aa-bc12-1234-5678-0987654321aa',
            ],
            'icon' => [
                'type' => 'emoji',
                'emoji' => '📧',
            ],
            'properties' => [
                'Full name' => [
                    'title' => [
                        [
                            'type' => 'text',
                            'text' => ['content' => 'linyows'],
                        ]
                    ],
                ],
                'Email address' => [
                    'email' => 'linyows@foo.example',
                ],
                'IP' => [
                    'rich_text' => [
                        [
                            'type' => 'text',
                            'text' => ['content' => '192.168.10.1'],
                        ]
                    ]
                ],
            ],
            'children' => [
                [
                    'object' => 'block',
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => 'Yo!'],
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $url = 'https://api.notion.com/v1/pages';
        $dummy = curl_init($url);
        $wrapper->shouldReceive('curl_init')->once($url)->andReturn($dummy);
        $wrapper->shouldReceive('curl_setopt')->with($dummy, CURLOPT_HTTPHEADER, $header)->andReturn(true);
        $wrapper->shouldReceive('curl_setopt')->with($dummy, CURLOPT_POSTFIELDS, json_encode($body))->andReturn(true);
        $wrapper->shouldReceive('curl_setopt')->with($dummy, CURLOPT_RETURNTRANSFER, true)->andReturn(true);
        $wrapper->shouldReceive('curl_exec')->once()->andReturn('{}');
        $wrapper->shouldReceive('curl_close')->once();
        $this->assertNotNull($slot->save());
    }
}