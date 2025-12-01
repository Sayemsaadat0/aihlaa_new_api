<?php

// Add this to config/services.php

return [
    // ... other services

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'whatsapp_to' => env('TWILIO_WHATSAPP_TO'),
    ],
];

