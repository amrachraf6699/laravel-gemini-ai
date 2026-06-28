# Laravel Gemini AI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amrachraf6699/laravel-gemini-ai.svg?style=flat-square)](https://packagist.org/packages/amrachraf6699/laravel-gemini-ai)
[![Total Downloads](https://img.shields.io/packagist/dt/amrachraf6699/laravel-gemini-ai.svg?style=flat-square)](https://packagist.org/packages/amrachraf6699/laravel-gemini-ai)

A Laravel package for easy integration with Google's Gemini API. It supports the Gemini Interactions API, text generation, structured JSON output, multi-turn chat, image generation and editing, image analysis, embeddings, token counting, and common Gemini tools.

## Requirements

- PHP 8.0 or higher
- Laravel 8.0 through 12.x
- Google Gemini API key from [Google AI Studio](https://ai.google.dev/)

## Installation

```bash
composer require amrachraf6699/laravel-gemini-ai
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=gemini-config
```

Add your API key to `.env`:

```bash
GEMINI_API_KEY=your-api-key-here
```

Optional model overrides:

```bash
GEMINI_TEXT_MODEL=gemini-3.5-flash
GEMINI_IMAGE_MODEL=gemini-3.1-flash-image
GEMINI_VISION_MODEL=gemini-3.5-flash
GEMINI_EMBEDDING_MODEL=gemini-embedding-2
```

## Usage

### Interactions API

Use `interact()` for new Gemini features. It returns parsed helpers and the raw API response.

```php
use Amrachraf6699\LaravelGeminiAi\Facades\GeminiAi;

$response = GeminiAi::interact('Write a welcome email for a SaaS trial user.', [
    'system_instruction' => 'You write concise, friendly product emails.',
    'generation_config' => [
        'temperature' => 0.6,
    ],
]);

echo $response['text'];

// Available helpers:
// $response['interaction_id']
// $response['text']
// $response['image']
// $response['images']
// $response['steps']
// $response['usage']
// $response['raw']
```

### Text Generation

Existing text generation still works:

```php
$response = GeminiAi::generateText('Tell me about black holes.');

$raw = GeminiAi::generateText('Write a short story about space exploration.', [
    'model' => 'gemini-3.5-flash',
    'raw' => true,
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 1000,
    ],
]);
```

### Structured JSON

```php
$response = GeminiAi::generateJson(
    'Extract the customer name and requested plan from this sentence: Sarah wants Pro.',
    [
        'type' => 'object',
        'properties' => [
            'customer_name' => ['type' => 'string'],
            'plan' => ['type' => 'string'],
        ],
        'required' => ['customer_name', 'plan'],
    ]
);

$data = $response['json'];
```

If the model returns invalid JSON, `json` is `null`, `json_error` contains the decode error, and `text` contains the raw model output.

### Multi-turn Chat

```php
$first = GeminiAi::chat('My app is a Laravel CRM. Suggest a homepage headline.');

$second = GeminiAi::chat(
    'Make it shorter.',
    $first['interaction_id']
);

echo $second['text'];
```

### Tools

```php
$response = GeminiAi::interact('What changed in Laravel recently?', [
    'tools' => [
        GeminiAi::googleSearchTool(),
    ],
]);

$response = GeminiAi::interact('Summarize https://laravel.com/docs', [
    'tools' => [
        GeminiAi::urlContextTool(),
    ],
]);

$response = GeminiAi::interact('Calculate compound growth for 12% over 5 years.', [
    'tools' => [
        GeminiAi::codeExecutionTool(),
    ],
]);
```

### Image Generation and Editing

```php
$response = GeminiAi::generateImage('A clean product mockup of a Laravel dashboard.', [
    'aspect_ratio' => '16:9',
    'image_size' => '2K',
]);

echo '<img src="'.$response['image_url'].'" alt="Generated image">';
```

Use reference images for edits or style transfer:

```php
$response = GeminiAi::generateImage('Replace the background with a bright office.', [
    'image' => public_path('uploads/product-photo.png'),
    'mime_type' => 'image/png',
]);
```

### Image Analysis

```php
$imageFile = $request->file('image');
$response = GeminiAi::processImageText('Describe this image in detail.', $imageFile);

$response = GeminiAi::processImageText('What is in this picture?', public_path('images/example.jpg'));

$imageContent = file_get_contents('path/to/image.jpg');
$response = GeminiAi::processImageText('Analyze this image.', $imageContent, [
    'mime_type' => 'image/jpeg',
]);
```

### Embeddings

```php
$vector = GeminiAi::embed('Laravel makes building web applications productive.');
```

Use `raw` to inspect the full embedding response:

```php
$response = GeminiAi::embed('Semantic search input.', [
    'raw' => true,
]);
```

### Token Counting

```php
$tokens = GeminiAi::countTokens('Estimate this prompt before sending it.');

echo $tokens['totalTokens'] ?? 0;
```

## Error Handling

The package throws exceptions when errors occur:

```php
try {
    $response = GeminiAi::generateText('Tell me a joke.');
} catch (\Exception $e) {
    Log::error('Gemini API Error: '.$e->getMessage());
}
```

## Available Options

Most methods support an `options` array.

| Option | Description |
|--------|-------------|
| `model` | Custom model to use |
| `raw` | Return the full API response for methods that support raw output |
| `generationConfig` / `generation_config` | Generation settings such as temperature and token limits |
| `system_instruction` | System instruction for Interactions |
| `response_format` | Text, JSON Schema, or image response format |
| `tools` | Tool declarations such as Google Search, URL Context, and Code Execution |
| `previous_interaction_id` | Continue a server-side Interactions conversation |
| `store` | Store an interaction for later continuation |
| `background` | Request background execution when supported by the API |
| `mime_type` | Input image MIME type fallback |
| `aspect_ratio` | Image output aspect ratio |
| `image_size` | Image output size |

## Contributing

Contributions are welcome. Please feel free to submit pull requests or open issues on GitHub.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
