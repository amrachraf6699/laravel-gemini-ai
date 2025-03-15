# Laravel Gemini AI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amrachraf6699/laravel-gemini-ai.svg?style=flat-square)](https://packagist.org/packages/amrachraf6699/laravel-gemini-ai)
[![Total Downloads](https://img.shields.io/packagist/dt/amrachraf6699/laravel-gemini-ai.svg?style=flat-square)](https://packagist.org/packages/amrachraf6699/laravel-gemini-ai)

A Laravel package for easy integration with Google's Gemini AI API. This package supports text generation, image generation, and image analysis through a simple and easy-to-use interface.

## Requirements

- PHP 8.0 or higher
- Laravel 8.0 or higher
- Google Gemini AI API key (you can get it from [Google AI Studio](https://ai.google.dev/))

## Installation

You can install the package via Composer:

```bash
composer require amrachraf6699/laravel-gemini-ai
```

## Configuration

After installing the package, publish the configuration file:

```bash
php artisan vendor:publish --tag=gemini-config
```

Then, add your API key to your `.env` file:

```bash
GEMINI_API_KEY=your-api-key-here
```

You can also customize the models used:

```bash
GEMINI_TEXT_MODEL=gemini-2.0-flash
GEMINI_IMAGE_MODEL=gemini-2.0-flash-exp
GEMINI_VISION_MODEL=gemini-2.0-flash
```

## Usage

### Text Generation

```php
use Amrachraf6699\LaravelGeminiAi\Facades\GeminiAi;

// Generate simple text
$response = GeminiAi::generateText("Tell me about black holes.");

// Using additional options
$response = GeminiAi::generateText("Write a short story about space exploration.", [
    'model' => 'gemini-2.0-pro', // Use a custom model
    'raw' => true, // Return the full response instead of just the text
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 1000
    ]
]);
```

### Image Generation

```php
// Generate an image
$response = GeminiAi::generateImage("A futuristic city skyline at sunset.");

// Using the response
if (!empty($response['image_url'])) {
    // Use $response['image_url'] to display the image
    echo '<img src="'.$response['image_url'].'" alt="Generated Image">';
    
    // Any text associated with the image
    echo $response['text'];
}
```

### Image Analysis

```php
// Analyze an image using an uploaded file
$imageFile = $request->file('image');
$response = GeminiAi::processImageText("Describe this image in detail.", $imageFile);

// Analyze an image using a file path
$response = GeminiAi::processImageText("What's in this picture?", public_path('images/example.jpg'));

// Analyze an image using binary content or base64 string
$imageContent = file_get_contents('path/to/image.jpg');
$response = GeminiAi::processImageText("Analyze this image.", $imageContent);
```

## Error Handling

The package throws exceptions when errors occur, so it's recommended to use try/catch:

```php
try {
    $response = GeminiAi::generateText("Tell me a joke.");
} catch (\Exception $e) {
    // Handle the error
    Log::error('Gemini API Error: ' . $e->getMessage());
}
```

## Available Options

All methods support an `options` array that can be used to customize API requests:

| Option | Description |
|--------|-------------|
| `model` | Custom model to use |
| `raw` | When set to `true`, returns the full API response instead of extracting content |
| `generationConfig` | Array of configuration options for the model (like temperature, tokens) |

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues on GitHub.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).