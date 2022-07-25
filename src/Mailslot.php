<?php
/**
 * Mailslot
 *
 * @category Mailslot
 * @package Mailslot
 * @copyright  Copyright (c) 2022 Tomohisa Oda <linyows@gmail.com>
 * @license MIT
 */

class Mailslot
{
    const NOTION_ENDPOINT = 'https://api.notion.com/v1/pages';

    /**
     * @var string Site domain
     */
    private $siteDomain;
    /**
     * @var string Site name
     */
    private $siteName;
    /**
     * @var string Notion token
     */
    private $notionToken;
    /**
     * @var string Notion database id
     */
    private $notionDbId;
    /**
     * @var string Notify to
     */
    private $notifyTo;
    /**
     * @var string Reply from
     */
    private $replyFrom;
    /**
     * @var string Datetime
     */
    private $now;
    /**
     * @var string Message from
     */
    private $name;
    /**
     * @var string Message email
     */
    private $email;
    /**
     * @var string Message
     */
    private $message;
    /**
     * @var string Client IP
     */
    private $remoteIp;
    /**
     * @var array Errors
     */
    private $errors;

    /**
     * __construct
     *
     *  $api = new Mailslot([
     *    'notion_token' => '',
     *    'notion_db_id' => '',
     *    'site_domain' => '',
     *    'site_name' => '',
     *    'notify_to' => '',
     *    'reply_from' => '',
     *  ]);
     *
     * @param array $config configuration settings.
     */
    public function __construct(array $config = [])
    {
        $this->notionToken = $config['notion_token'];
        $this->notionDbId = $config['notion_db_id'];
        $this->siteDomain = $config['site_domain'];
        $this->siteName = $config['site_name'];
        $this->notifyTo = $config['notify_to'];
        $this->replyFrom = $config['reply_from'];
        $this->header();
    }

    /**
     * header
     *
     * @return void
     */
    private function header()
    {
        $headers = [
            'Strict-Transport-Security: max-age=31536000; includeSubdomains; preload',
            'X-Frame-Options: DENY',
            'Vary: Accept, Accept-Encoding, Accept, X-Requested-With',
            'Content-Security-Policy: default-src \'none\'',
            "Access-Control-Allow-Origin: https://{$this->siteDomain}",
            'Access-Control-Allow-Headers: Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, Accept-Encoding, X-Requested-With, User-Agent',
            'Access-Control-Allow-Methods: POST, OPTIONS',
            'Referrer-Policy: origin-when-cross-origin, strict-origin-when-cross-origin',
            'X-Content-Type-Options: nosniff',
            'Content-Type: application/json; charset=utf-8',
        ];

        foreach ($headers as $v) {
            header($v);
        }
    }

    /**
     * setData
     *
     * @param array $data
     * @param string $remoteIp
     * @param string $now
     * @return self
     */
    public function setData(array $data, string $remoteIp, string $now)
    {
        $this->name = $data['name'];
        $this->email = $data['email'];
        $this->message = $data['message'];
        $this->removeIp = $remoteIp;
        return $this;
    }

    /**
     * isValid
     *
     * @return boolean
     */
    public function isValid()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: https://{$this->config['site_domain']}");
            exit(1);
        }

        $this->errors = [];
        if (is_null($this->name)) {
            array_push($this->errors, 'name is required');
        }
        if (is_null($this->email)) {
            array_push($this->errors, 'email is required');
        }
        if (is_null($this->message)) {
            array_push($this->errors, 'message is required');
        }

        return empty($this->errors);
    }

    /**
     * Errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * data
     */
    private function data(): string
    {
        return <<<EOL
Full name:
  {$this->name}
Email address:
  {$this->email}
Message:
  {$this->message}
EOL;
    }

    /**
     * footer
     */
    private function footer(): string
    {
        return <<<EOL
--
{$this->siteName}
https://{$this->siteDomain}
EOL;
    }

    /**
     * notify
     */
    public function notify(): self
    {
        $subject = "Contact from {$this->siteDomain}";
        $body = <<<EOL
Contact from {$this->siteDomain} here:
Date: {$this->now}
IP: {$this->remoteIp}

{$this->data()}

{$this->footer()}
EOL;
        $headers = ['From' => $this->mailFrom, 'Reply-To' => $this->replyTo];
        $this->mail($this->replyTo, $subject, $body, $headers);

        return $this
    }

    /**
     * reply
     */
    public function reply(): self
    {
        $subject = "Thanks for your message from {$this->siteDomain}";
        $body = <<<EOL
{$this->name}

It has accepted a your message to {$this->siteDomain}.

Date:
{$this->now}
Message:
{$this->message}

{$this->footer()}
EOL;
        $headers = ['From' => $this->mailFrom, 'Reply-To' => $this->replyTo];
        $this->mail($this->email, $subject, $body, $headers);

        return $this
    }

    /**
     * mail
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $headers
     */
    private function mail(string $to, string $subject, string $body, array $headers): void
    {
        mb_send_mail($to, $subject, $body, $headers);
    }

    /**
     * Save to Notion Database
     */
    public function save(): string
    {
        $headers = [
            "Authorization: Bearer {$this->notionToken}",
            'Content-Type: application/json',
            'Notion-Version: 2022-06-28',
        ];
        $data = json_encode($this->buildRequestBody());

        $ch = curl_init(self::NOTION_ENDPOINT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * Build request body for Notion
     */
    private function buildRequestBody(): string
    {
        $blocks = [];
        $data = explode('\n', $this->message);

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

        return [
            'parent' => [
                'type' => 'database_id',
                'database_id' => $this->notionDbId,
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
                            'text' => ['content' => $this->name],
                        ],
                    ],
                ],
                'Email' => [
                    'email' => $this->email,
                ],
                'IP' => [
                    'rich_text' => [
                        [
                            'type' => 'text',
                            'text' => ['content' => $this->remoteIp],
                        ],
                    ],
                ],
            ],
            'children' => $blocks,
        ];
    }
}
