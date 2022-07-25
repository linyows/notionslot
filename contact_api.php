<?php

/**
 * Contact API
 *
 * @author linyows <linyows@gmail.com>
 * @version $Id$
 */
class ContactAPI
{
    public $sitedomain;
    public $sitename;
    public $notiontoken;
    public $notiondbid;
    public $to;
    public $headers;

    public $now;
    public $name;
    public $email;
    public $message;
    public $remoteIp;

    public $errors;

    /**
     * showHeader
     *
     * @access public
     * @return void
     */
    function showHeader()
    {
        $headers = [
            'Strict-Transport-Security: max-age=31536000; includeSubdomains; preload',
            'X-Frame-Options: DENY',
            'Vary: Accept, Accept-Encoding, Accept, X-Requested-With',
            'Content-Security-Policy: default-src \'none\'',
            "Access-Control-Allow-Origin: https://{$this->sitedomain}",
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
     * isValid
     *
     * @access public
     * @return boolean
     */
    function isValid()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: https://{$this->sitedomain}");
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
     * data
     *
     * @access public
     * @return string
     */
    function data()
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
     *
     * @access public
     * @return string
     */
    function footer()
    {
        return <<<EOL
--
{$this->sitename}
https://{$this->sitedomain}
EOL;
    }

    /**
     * notify
     *
     * @access public
     * @return void
     */
    function notify()
    {
        $subject = "Contact from {$this->sitedomain}";
        $body = <<<EOL
Contact from {$this->sitedomain} here:
Date: {$this->now}
IP: {$this->remoteIp}

{$this->data()}

{$this->footer()}
EOL;
        mb_send_mail($this->to, $subject, $body, $this->headers);
    }

    /**
     * reply
     *
     * @access public
     * @return void
     */
    function reply()
    {
        $subject = "Thanks for your message from {$this->sitedomain}";
        $body = <<<EOL
{$this->name}

It has accepted a your message to {$this->sitedomain}.

Date:
{$this->now}
Message:
{$this->message}

{$this->footer()}
EOL;
        mb_send_mail($this->email, $subject, $body, $this->headers);
    }

    /**
     * saveToNotion
     *
     * @access public
     * @return string
     */
    function saveToNotion()
    {
        $endpoint = 'https://api.notion.com/v1/pages';
        $headers = [
            "Authorization: Bearer {$this->notiontoken}",
            'Content-Type: application/json',
            'Notion-Version: 2022-06-28',
        ];
        $data = json_encode($this->buildNotionRequestBody());

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * buildNotionRequestBody
     *
     * @access public
     * @return string
     */
    function buildNotionRequestBody()
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
                'database_id' => $this->notiondbid,
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
