@include('admin.partials.stub', [
    'title' => 'Escalations',
    'subtitle' => 'ის შემთხვევები სადაც ბოტმა შენი ჩარევა მოითხოვა',
    'heading' => 'Escalation queue',
    'body' => 'ცოცხალი feed ყველა escalation-ის + reason analytics. ამ ეტაპზე escalation-ები მონიშნულია Inbox-შიც, ფილტრით ⚠️ Escalated.',
    'links' => [
        '/admin/inbox' => 'Inbox → Escalated ფილტრი',
        '/admin/integrations#escalation' => 'Escalation settings',
    ],
])
