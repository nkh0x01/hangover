<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Debounce window
    |--------------------------------------------------------------------------
    | When a customer sends a message we wait between MIN and MAX seconds
    | before we even *consider* replying. If another message arrives the
    | timer resets — so the bot reads the entire thought, not fragments.
    */
    'debounce' => [
        'min_seconds' => (int) env('CHATBOT_DEBOUNCE_MIN', 5),
        'max_seconds' => (int) env('CHATBOT_DEBOUNCE_MAX', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Typing pacing
    |--------------------------------------------------------------------------
    | Outbound replies are paced to look like a human typing.
    | total_ms = clamp(min + per_char * len(text), min, max)
    */
    'typing' => [
        'min_ms'      => (int) env('CHATBOT_TYPING_MIN_MS', 800),
        'per_char_ms' => (int) env('CHATBOT_TYPING_PER_CHAR_MS', 22),
        'max_ms'      => (int) env('CHATBOT_TYPING_MAX_MS', 6000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Working hours
    |--------------------------------------------------------------------------
    | Outside working hours the bot still replies, but flags the
    | conversation so a human follows up in the morning. Set both to 0
    | to disable the gate (24/7 mode).
    */
    'working_hours' => [
        'start'    => (int) env('CHATBOT_WORKING_HOURS_START', 10),
        'end'      => (int) env('CHATBOT_WORKING_HOURS_END', 22),
        'timezone' => env('APP_TIMEZONE', 'Asia/Tbilisi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI confidence gate
    |--------------------------------------------------------------------------
    | Every Claude reply is asked to self-report a confidence score.
    | Below this floor the reply is suppressed and the conversation
    | escalates to a human.
    */
    'ai' => [
        'min_confidence' => (float) env('CHATBOT_MIN_CONFIDENCE', 0.62),
        'history_turns'  => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tone presets
    |--------------------------------------------------------------------------
    | Picked dynamically by ToneAdapter based on the customer's last few
    | messages (length, punctuation, emoji density, sentiment).
    */
    'tones' => [
        'short_punchy' => [
            'description' => 'Customer writes 1-line, quick replies. Match length.',
            'max_words'   => 18,
            'emoji_rate'  => 0.1,
        ],
        'friendly_warm' => [
            'description' => 'Customer is emotional or polite. Mirror warmth.',
            'max_words'   => 60,
            'emoji_rate'  => 0.4,
        ],
        'educational' => [
            'description' => 'Customer is confused or asking technical questions.',
            'max_words'   => 90,
            'emoji_rate'  => 0.1,
        ],
        'sales_focused' => [
            'description' => 'Customer signals buying intent. Close the deal.',
            'max_words'   => 50,
            'emoji_rate'  => 0.2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick replies / canned templates
    |--------------------------------------------------------------------------
    | Available to admins as one-click templates. Variables in {{...}}
    | are interpolated from the conversation context.
    */
    'quick_replies' => [
        'greeting'       => 'გამარჯობა! 👋 რით შემიძლია დაგეხმაროთ?',
        'check_stock'    => 'ერთი წუთით, ვამოწმებ საწყობს.',
        'in_dm'          => 'მოგწერთ პირად შეტყობინებაში ❤️',
        'thanks'         => 'მადლობა, რომ აირჩიეთ Gadget! 🙏',
        'branch_offer'   => 'შეგიძლიათ ფილიალში მობრძანდეთ ან მოვაწოდოთ კურიერით.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked topics
    |--------------------------------------------------------------------------
    | If the customer's message matches one of these patterns the bot
    | will not engage and will escalate or stay silent.
    */
    'blocked_topics' => [
        'politics', 'religion', 'adult', 'gambling', 'crypto_scam',
    ],

    /*
    |--------------------------------------------------------------------------
    | Abandoned chat follow-up
    |--------------------------------------------------------------------------
    | If the customer asked about a product and disappeared, we follow
    | up after the configured delay (once, never spammy).
    */
    'follow_up' => [
        'enabled'         => true,
        'delay_minutes'   => 90,
        'max_per_customer'=> 1,
        'quiet_hours'     => [23, 9], // [start, end]
    ],
];
