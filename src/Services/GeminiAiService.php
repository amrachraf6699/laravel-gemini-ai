<?php

namespace Amrachraf6699\LaravelGeminiAi\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class GeminiAiService
{
    protected $apiKey;
    protected $baseUrl;
    protected $models;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');
        $this->baseUrl = config('gemini.base_url');
        $this->models = config('gemini.models');
    }

    /**
     * Generate text from a prompt
     *
     * @param string $prompt The prompt to generate text from
     * @param array $options Additional options for the API
     * @return array|string The generated text or response data
     */
    public function generateText(string $prompt, array $options = [])
    {
        try {
            $model = $options['model'] ?? $this->models['text'];
            $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ];

            if (!empty($options['generationConfig'])) {
                $payload['generationConfig'] = $options['generationConfig'];
            }

            $response = Http::post($url, $payload);
            $this->validateResponse($response);
            $data = $response->json();

            return $options['raw'] ?? false 
                ? $data 
                : $this->extractTextContent($data);

        } catch (\Exception $e) {
            Log::error('Gemini API Error (Text): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate an image from a text prompt
     *
     * @param string $prompt The prompt to generate the image from
     * @param array $options Additional options for the API
     * @return array The response containing the image data
     */
    public function generateImage(string $prompt, array $options = [])
    {
        try {
            $model = $options['model'] ?? $this->models['image'];
            $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $prompt]]
                    ]
                ],
                'generationConfig' => [
                    'response_modalities' => ['TEXT', 'IMAGE']
                ]
            ];

            // Merge any additional generation config options
            if (!empty($options['generationConfig'])) {
                $payload['generationConfig'] = array_merge(
                    $payload['generationConfig'], 
                    $options['generationConfig']
                );
            }

            $response = Http::post($url, $payload);
            $this->validateResponse($response);
            $data = $response->json();

            if ($options['raw'] ?? false) {
                return $data;
            }

            return $this->extractImageContent($data);

        } catch (\Exception $e) {
            Log::error('Gemini API Error (Image): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process an image with a text prompt
     *
     * @param string $prompt The text prompt to analyze the image
     * @param mixed $image The image file (can be a file path, uploaded file, or base64 string)
     * @param array $options Additional options for the API
     * @return array|string The response data or extracted text
     */
    public function processImageText(string $prompt, $image, array $options = [])
    {
        try {
            $model = $options['model'] ?? $this->models['vision'];
            $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

            // Convert image to base64 if it's not already
            $base64Image = $this->prepareImage($image);

            $payload = [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $prompt],
                            [
                                "inline_data" => [
                                    "mime_type" => "image/jpeg",
                                    "data" => $base64Image,
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // Add generation config if provided
            if (!empty($options['generationConfig'])) {
                $payload['generationConfig'] = $options['generationConfig'];
            }

            $response = Http::post($url, $payload);
            $this->validateResponse($response);
            $data = $response->json();

            return $options['raw'] ?? false 
                ? $data 
                : $this->extractTextContent($data);

        } catch (\Exception $e) {
            Log::error('Gemini API Error (Vision): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate the API response
     *
     * @param Response $response
     * @return void
     * @throws \Exception
     */
    protected function validateResponse(Response $response)
    {
        if ($response->failed()) {
            $error = $response->json();
            $message = $error['error']['message'] ?? 'Unknown API error';
            throw new \Exception("Gemini API Error: {$message}", $response->status());
        }
    }

    /**
     * Extract text content from the API response
     *
     * @param array $data The response data
     * @return string The extracted text
     */
    protected function extractTextContent(array $data)
    {
        if (empty($data['candidates'][0]['content']['parts'])) {
            return '';
        }

        $content = '';
        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }
        }

        return $content;
    }

    /**
     * Extract image content from the API response
     *
     * @param array $data The response data
     * @return array The image data
     */
    protected function extractImageContent(array $data)
    {
        $result = [
            'text' => '',
            'image_url' => null
        ];

        if (empty($data['candidates'][0]['content']['parts'])) {
            return $result;
        }

        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $result['text'] .= $part['text'];
            }
            if (!empty($part['inlineData']['data'])) {
                $result['image_url'] = 'data:image/png;base64,' . $part['inlineData']['data'];
            }
        }

        return $result;
    }

    /**
     * Prepare image for API submission
     *
     * @param mixed $image
     * @return string Base64 encoded image
     */
    protected function prepareImage($image)
    {
        // If already a base64 string
        if (is_string($image) && preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $image)) {
            return $image;
        }
        
        // If it's a file path
        if (is_string($image) && file_exists($image)) {
            return base64_encode(file_get_contents($image));
        }
        
        // If it's an uploaded file (UploadedFile instance)
        if (is_object($image) && method_exists($image, 'getRealPath')) {
            return base64_encode(file_get_contents($image->getRealPath()));
        }
        
        // If it's binary data
        if (is_string($image) && !file_exists($image)) {
            return base64_encode($image);
        }
        
        throw new \InvalidArgumentException('Invalid image format provided.');
    }
}