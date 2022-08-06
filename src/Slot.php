<?php
/**
 * Notionslot
 *
 * @package Notionslot
 * @copyright  Copyright (c) 2022 Tomohisa Oda <linyows@gmail.com>
 * @license MIT
 */

namespace Notionslot;

use \Notionslot\Wrapper;
use \Notionslot\ConfigMissingError;

class Slot
{
    public static function api(array $config = [], array $server = [], array $data = [], $logger = null, $wrapper = Wrapper::class): string|false
    {
        $res = [
            'ok' => true,
            'errors' => [],
        ];

        $slot = new Slot($config, $wrapper);
        if ($slot->sendHeader($server)->setData($data)->isValid()) {
            $notionRes = $slot->notify()->reply()->save();
            if (!is_null($logger)) {
                $logger($notionRes);
            }
        } else {
            $wrapper::http_response_code(422);
            $res = [
                'ok' => false,
                'errors' => $slot->errors(),
            ];
        }

        return $wrapper::json_encode($res);
    }

    private $config = [
        'notion_endpoint' => 'https://api.notion.com/v1/pages',
        'notion_emoji' => '📧',
        'notion_token' => '',
        'notion_db_id' => '',
        'site_domain' => '',
        'site_name' => '',
        'notify_to' => '',
        'reply_to' => '',
        'mail_from' => '',
        'mail_to_key' => 'email',
        'params' => [
            [
                'key' => 'name',
                'required' => true,
                'notion_name' => 'Full name',
                'notion_type' => 'title'
            ],
            [
                'key' => 'email',
                'required' => true,
                'notion_name' => 'Email address',
                'notion_type' => 'email'
            ],
            [
                'key' => 'ip',
                'required' => true,
                'notion_name' => 'IP address',
                'notion_type' => 'rich_text'
            ],
            [
                'key' => 'message',
                'required' => true,
                'notion_name' => 'Message',
                'notion_type' => 'block'
            ],
        ],
    ];

    private $data = [];
    private $errors = [];
    private $wrapper;

    public function __construct(array $config = [], $wrapper = Wrapper::class)
    {
        $this->config = array_merge($this->config, $config);
        $this->isValidConfig();
        $this->wrapper = $wrapper;
    }

    private function isValidConfig(): void
    {
        foreach ($this->config as $k => $v) {
            if (empty($v)) {
                throw new ConfigMissingError("{$k} is required as notionslot config");
            }
        }
    }

    private function httpHeader(): array
    {
        return [
            'Strict-Transport-Security: max-age=31536000; includeSubdomains; preload',
            'X-Frame-Options: DENY',
            'Vary: Accept, Accept-Encoding, Accept, X-Requested-With',
            'Content-Security-Policy: default-src \'none\'',
            "Access-Control-Allow-Origin: https://{$this->config['site_domain']}",
            'Access-Control-Allow-Headers: Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, Accept-Encoding, X-Requested-With, User-Agent',
            'Access-Control-Allow-Methods: POST, OPTIONS',
            'Referrer-Policy: origin-when-cross-origin, strict-origin-when-cross-origin',
            'X-Content-Type-Options: nosniff',
            'Content-Type: application/json; charset=utf-8',
        ];
    }

    public function sendHeader(array $server): self
    {
        if (isset($server['REQUEST_METHOD']) && $server['REQUEST_METHOD'] !== 'POST') {
            $this->wrapper::header("Location: https://{$this->config['site_domain']}");
            $this->wrapper::exit(1);
            return $this;
        }

        foreach ($this->httpHeader() as $v) {
            $this->wrapper::header($v);
        }

        return $this;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function isValid(): bool
    {
        $this->errors = [];

        foreach ($this->config['params'] as $v) {
            if ($v['required'] && !(isset($this->data[$v['key']]) && !empty($this->data[$v['key']]))) {
                array_push($this->errors, "{$v['key']} is required");
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function notificationBody(): string
    {
        $body = "\n";
        foreach ($this->config['params'] as $v) {
            $body .= "{$v['notion_name']}:\n";
            $body .= "  {$this->data[$v['key']]}\n";
        }
        return $body;
    }

    private function notificationFooter(): string
    {
        return <<<EOL
--
{$this->config['site_name']}
https://{$this->config['site_domain']}
EOL;
    }

    public function notify(): self
    {
        $subject = "Contact from {$this->config['site_domain']}";
        $body = <<<EOL
Contact from {$this->config['site_domain']} here:
{$this->notificationBody()}
{$this->notificationFooter()}
EOL;
        $headers = [
            'From' => $this->config['mail_from'],
            'Reply-To' => $this->config['reply_to'],
        ];
        $this->wrapper::mb_send_mail($this->config['notify_to'], $subject, $body, $headers);

        return $this;
    }

    public function reply(): self
    {
        $subject = "Thanks for your message from {$this->config['site_domain']}";
        $body = <<<EOL
It has accepted a your message to {$this->config['site_domain']}.
{$this->notificationBody()}
{$this->notificationFooter()}
EOL;
        $headers = [
            'From' => $this->config['mail_from'],
            'Reply-To' => $this->config['reply_to'],
        ];
        $this->wrapper::mb_send_mail($this->data[$this->config['mail_to_key']], $subject, $body, $headers);

        return $this;
    }

    public function save(): string|bool
    {
        $handle = $this->wrapper::curl_init($this->config['notion_endpoint']);
        $this->wrapper::curl_setopt($handle, CURLOPT_HTTPHEADER, $this->requestHeader());
        $this->wrapper::curl_setopt($handle, CURLOPT_POSTFIELDS, $this->wrapper::json_encode($this->requestBody()));
        $this->wrapper::curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $result = $this->wrapper::curl_exec($handle);
        $this->wrapper::curl_close($handle);

        return $result;
    }

    private function dBProperties(): array
    {
        $properties = [];

        foreach ($this->config['params'] as $v) {
            $content = $this->data[$v['key']];
            switch ($v['notion_type']) {
                case 'title':
                    $properties[$v['notion_name']] = [
                        'title' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => $content],
                            ],
                        ],
                    ];
                    break;

                case 'email':
                    $properties[$v['notion_name']] = [
                        'email' => $content,
                    ];
                    break;

                case 'rich_text':
                    $properties[$v['notion_name']] = [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => $content],
                            ],
                        ],
                    ];
                    break;

                case 'block':
                    break;

                default:
                    array_push($this->errors, "{$v['notion_type']} is undefined notion property type");
                    break;
            }
        }

        return $properties;
    }

    private function pageBlocks(): array
    {
        $blocks = [];

        foreach ($this->config['params'] as $v) {
            if ($v['notion_type'] !== 'block') {
                continue;
            }
            $data = explode('\n', $this->data[$v['key']]);
            foreach ($data as $v) {
                array_push($blocks, [
                    'object' => 'block',
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => $v],
                            ],
                        ],
                    ],
                ]);
            }
        }

        return $blocks;
    }

    private function requestHeader(): array
    {
        return [
            "Authorization: Bearer {$this->config['notion_token']}",
            'Content-Type: application/json',
            'Notion-Version: 2022-06-28',
        ];
    }

    private function requestBody(): array
    {
        return [
            'parent' => [
                'type' => 'database_id',
                'database_id' => $this->config['notion_db_id'],
            ],
            'icon' => [
                'type' => 'emoji',
                'emoji' => $this->config['notion_emoji'],
            ],
            'properties' => $this->dbProperties(),
            'children' => $this->pageBlocks(),
        ];
    }
}
