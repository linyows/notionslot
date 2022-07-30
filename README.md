Mailslot
==

Mailslot stores messages in database on Notion for email notifications.
It is supposed to be used in the contact form of web hosting.

<a href="https://github.com/linyows/mailslot/actions/workflows/build.yml"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/workflow/status/linyows/mailslot/Build%20by%20matrix?style=for-the-badge"></a>

Usage
--

API mode:

```php
$config = [
  'notion_token' => 'secret_**********************************',
  'notion_db_id' => '123456aa-bc12-1234-5678-0987654321aa',
  'site_domain' => 'foo.example',
  'site_name' => 'My Foo',
  'notify_to' => 'me@foo.example',
  'reply_to' => 'hello@foo.example',
  'mail_from' => 'noreply@foo.example',
];
$data = array_merge($_POST, ['ip' => $_SERVER['REMOTE_ADDR']]);

echo Mailslot::api($config, $_SERVER, $data);
```

Library mode:

```php
$config = [
  'notion_token' => 'secret_**********************************',
  'notion_db_id' => '123456aa-bc12-1234-5678-0987654321aa',
  'site_domain' => 'foo.example',
  'site_name' => 'My Foo',
  'notify_to' => 'me@foo.example',
  'reply_to' => 'hello@foo.example',
  'mail_from' => 'noreply@foo.example',
];
$data = array_merge($_POST, ['ip' => $_SERVER['REMOTE_ADDR']]);

$res = [
    'ok' => true,
    'errors' => [],
];

$slot = new Mailslot($config);
if ($slot->sendHeader($_SERVER)->setData($data)->isValid()) {
    $notionRes = $slot->notify()->reply()->save();
} else {
    http_response_code(422);
    $res = [
        'ok' => false,
        'errors' => $slot->errors(),
    ];
}

return json_encode($res);
```

Author
--

[@linyows](https://github.com/linyows)
