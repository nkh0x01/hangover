<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Owner WhatsApp targets
    |--------------------------------------------------------------------------
    | One or more E.164 phone numbers to notify when the bot escalates.
    | First number is "primary" and is paged immediately. Additional
    | numbers are paged after `secondary_delay_seconds` if unread.
    */
    'whatsapp_targets' => array_values(array_filter(array_map('trim', explode(',', (string) env('ESCALATION_WHATSAPP_TO', ''))))),
    'secondary_delay_seconds' => 120,

    /*
    |--------------------------------------------------------------------------
    | Admin deep-link
    |--------------------------------------------------------------------------
    | The escalation message includes a clickable link to the
    | conversation in the unified inbox.
    */
    'admin_url' => env('ESCALATION_ADMIN_URL', 'https://admin.gadget.ge/inbox'),

    /*
    |--------------------------------------------------------------------------
    | Triggers
    |--------------------------------------------------------------------------
    | Each rule produces an escalation when it fires. Order doesn't matter;
    | first hit wins.
    */
    'triggers' => [
        'intent_complaint' => ['type' => 'intent',     'value' => 'complaint',     'urgency' => 'high'],
        'intent_refund' => ['type' => 'intent',     'value' => 'refund',        'urgency' => 'high'],
        'intent_warranty' => ['type' => 'intent',     'value' => 'warranty',      'urgency' => 'medium'],
        'intent_legal' => ['type' => 'intent',     'value' => 'legal',         'urgency' => 'high'],
        'intent_manager_req' => ['type' => 'intent',     'value' => 'manager_request', 'urgency' => 'high'],
        'low_confidence' => ['type' => 'confidence', 'value' => 0.62,            'urgency' => 'low'],
        'negative_sentiment' => ['type' => 'sentiment',  'value' => -0.6,            'urgency' => 'medium'],
        'product_unavailable' => ['type' => 'flag',       'value' => 'unavailable',   'urgency' => 'medium'],
        'custom_discount' => ['type' => 'intent',     'value' => 'discount_request', 'urgency' => 'low'],
        'payment_issue' => ['type' => 'flag',       'value' => 'payment_failed', 'urgency' => 'high'],
        'toxic_language' => ['type' => 'flag',       'value' => 'toxic',         'urgency' => 'medium'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phrase triggers (Georgian)
    |--------------------------------------------------------------------------
    | Substring match (case-insensitive). Used by EscalationDetector
    | as a cheap pre-filter before the AI classifier.
    */
    'phrase_triggers' => [
        'მენეჯერი',
        'მფლობელი',
        'უფროსი',
        'საჩივარი',
        'ვუჩივი',
        'სასამართლო',
        'მოვითხოვ',
        'გავცვალო',
        'უკან დავაბრუნო',
        'ფული დამიბრუნე',
        'გაფუჭდა',
        'არ მუშაობს',
        'საგარანტიო',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-pause AI on escalation
    |--------------------------------------------------------------------------
    | When true, after escalation the AI stops replying entirely and the
    | conversation waits for a human. Recommended: true.
    */
    'pause_ai_after_escalation' => true,
];
