<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gemini API Key
    |--------------------------------------------------------------------------
    |
    | Here you may specify your Gemini API key. This will be used to authenticate
    | with the Gemini API - you can get your API key from the Google AI Studio.
    |
    */
    'api_key' => env('GEMINI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Gemini API Base URL
    |--------------------------------------------------------------------------
    |
    | This is the base URL for the Gemini AI API endpoints.
    |
    */
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
    |--------------------------------------------------------------------------
    | Default Gemini Models
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default models to be used for different operations.
    |
    */
    'models' => [
        'text' => env('GEMINI_TEXT_MODEL', 'gemini-2.0-flash'),
        'image' => env('GEMINI_IMAGE_MODEL', 'gemini-2.0-flash-exp'),
        'vision' => env('GEMINI_VISION_MODEL', 'gemini-2.0-flash'),
    ],
];