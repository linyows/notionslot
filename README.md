Notionslot
==

<a href="https://github.com/linyows/mailslot/actions/workflows/build.yml"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/workflow/status/linyows/mailslot/Build?style=for-the-badge"></a>

Notionslot stores messages in database on Notion for email notifications.
It is supposed to be used in the contact form of web hosting.

Usage
--

API mode:

```php
use \Notionslot\Slot;

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

echo Slot::api($config, $_SERVER, $data);
```

Library mode:

```php
use \Notionslot\Slot;

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

$slot = new Slot($config);
if ($slot->sendHeader($_SERVER)->setData($data)->isValid()) {
    $notionRes = $slot->notify()->reply()->save();
} else {
    http_response_code(422);
    $res = [
        'ok' => false,
        'errors' => $slot->errors(),
    ];
}

echo json_encode($res);
```

Installation
--

The recommended way to install Guzzle is through Composer.

```sh
$ composer require linyows/notionslot
```

Configuration
--

Please specify notion token, website domain, mail from, reply to, etc by config.

Name            | Description
--              | --
notion_endpoint | Page API endpoint for Notion
notion_emoji    | Emoji used for Notion pages
notion_token    | Credential for Notion API needs write permission
notion_db_id    | Database ID on Notion
site_domain     | Your website domain
notify_to       | Email address to notify
reply_to        | Reply-to header for SMTP
mail_from       | From header for SMTP
mail_to_key     | A key that specifies an email address
params          | See below

Custom
--

The default Notion databse property names are `Full name(title)`, `Email address(email)`, `IP(rich_text)`.
You can change the property name and type from the settings.

Name        | Description
--          | --
key         | Use as HTTP Post params key
required    | Whether a param is required
notion_name | User defined property name on notion database
notion_type | Property type on notion database

Author
--

[@linyows](https://github.com/linyows)
