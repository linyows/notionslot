Mailslot
==

Mailslot stores messages in database on Notion for email notifications.
It is supposed to be used in the contact form of web hosting.

Usage
--

```php
$slot = new Mailslot([
  'notion_token' => 'secret_**********************************',
  'notion_db_id' => '123456aa-bc12-1234-5678-0987654321aa',
  'site_domain' => 'foo.example',
  'site_name' => 'My Foo',
  'notify_to' => 'me@foo.example',
  'reply_from' => 'noreply@foo.example',
]);

$ip = $_SERVER['REMOTE_ADDR'];
$now = date('Y-m-d H:i:s');
$res = ['result' => 'ok', 'errors' => null];

if ($slot->setData($_POST, $ip, $now)->isValid()) {
    $slot->notify()->reply()->save();
} else {
    $res = ['result' => 'ng', 'errors' => $slot->errors()];
}

echo json_encode($res);
```

Author
--

[@linyows](https://github.com/linyows)
