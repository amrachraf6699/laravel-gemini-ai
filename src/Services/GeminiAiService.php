<?php

namespace Amrachraf6699\LaravelGeminiAi\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiService
{
    protected $apiKey;
    protected $baseUrl;
    protected $models;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');
        $this->baseUrl = rtrim(config('gemini.base_url'), '/');
        $this->models = config('gemini.models', []);
    }

    /**
     * Create a Gemini interaction using the recommended Interactions API.
     *
     * @param mixed $input A string, content block, list of content blocks, turns, or steps.
     * @param array $options Additional options for the API.
     * @return array
     */
    public function interact($input, array $options = [])
    {
        try {
            $payload = [
                'input' => $this->prepareInteractionInput($input),
            ];

            if (array_key_exists('agent', $options)) {
                $payload['agent'] = $options['agent'];
            }

            if (array_key_exists('model', $options) || !array_key_exists('agent', $options)) {
                $payload['model'] = $options['model'] ?? $this->model('text', 'gemini-3.5-flash');
            }

            $this->applyInteractionOptions($payload, $options);

            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/interactions", $payload);

            $this->validateResponse($response);
            $data = $response->json();

            return $options['raw'] ?? false
                ? $data
                : $this->formatInteractionResponse($data);
        } catch (\Exception $e) {
            Log::error('Gemini API Error (Interaction): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate structured JSON with an Interactions response_format schema.
     *
     * @param string $prompt The prompt to generate JSON from.
     * @param array $schema JSON Schema for the response.
     * @param array $options Additional options for the API.
     * @return array
     */
    public function generateJson(string $prompt, array $schema, array $options = [])
    {
        $options['response_format'] = $options['response_format'] ?? [
            'type' => 'text',
            'mime_type' => 'application/json',
            'schema' => $schema,
        ];

        $response = $this->interact($prompt, $options);

        if ($options['raw'] ?? false) {
            return $response;
        }

        $text = $response['text'] ?? '';
        $decoded = json_decode($text, true);

        $response['json'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        $response['json_error'] = json_last_error() === JSON_ERROR_NONE ? null : json_last_error_msg();

        return $response;
    }

    /**
     * Continue a multi-turn conversation using previous_interaction_id.
     *
     * @param string $prompt The user message.
     * @param string|null $previousInteractionId The previous interaction id.
     * @param array $options Additional options for the API.
     * @return array
     */
    public function chat(string $prompt, ?string $previousInteractionId = null, array $options = [])
    {
        if ($previousInteractionId !== null) {
            $options['previous_interaction_id'] = $previousInteractionId;
        }

        $options['store'] = $options['store'] ?? true;

        return $this->interact($prompt, $options);
    }

    /**
     * Build a Google Search tool declaration.
     *
     * @param array $options Additional tool options.
     * @return array
     */
    public function googleSearchTool(array $options = [])
    {
        return array_merge(['type' => 'google_search'], $options);
    }

    /**
     * Build a URL Context tool declaration.
     *
     * @param array $options Additional tool options.
     * @return array
     */
    public function urlContextTool(array $options = [])
    {
        return array_merge(['type' => 'url_context'], $options);
    }

    /**
     * Build a Code Execution tool declaration.
     *
     * @param array $options Additional tool options.
     * @return array
     */
    public function codeExecutionTool(array $options = [])
    {
        return array_merge(['type' => 'code_execution'], $options);
    }

    /**
     * Generate text from a prompt using the legacy generateContent endpoint.
     *
     * @param string $prompt The prompt to generate text from.
     * @param array $options Additional options for the API.
     * @return array|string The generated text or response data.
     */
    public function generateText(string $prompt, array $options = [])
    {
        try {
            $model = $options['model'] ?? $this->model('text', 'gemini-3.5-flash');
            $url = "{$this->baseUrl}/models/{$model}:generateContent";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ];

            if (!empty($options['generationConfig'])) {
                $payload['generationConfig'] = $options['generationConfig'];
            }

            if (!empty($options['generation_config'])) {
                $payload['generationConfig'] = $options['generation_config'];
            }

            if (!empty($options['systemInstruction'])) {
                $payload['systemInstruction'] = $this->prepareGenerateContentContent($options['systemInstruction']);
            }

            if (!empty($options['system_instruction'])) {
                $payload['systemInstruction'] = $this->prepareGenerateContentContent($options['system_instruction']);
            }

            if (!empty($options['tools'])) {
                $payload['tools'] = $options['tools'];
            }

            $response = Http::withHeaders($this->headers())->post($url, $payload);
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
     * Generate or edit an image from a text prompt.
     *
     * @param string $prompt The prompt to generate or edit the image.
     * @param array $options Additional options for the API.
     * @return array The response containing the image data.
     */
    public function generateImage(string $prompt, array $options = [])
    {
        try {
            $options['model'] = $options['model'] ?? $this->model('image', 'gemini-3.1-flash-image');
            $options['response_modalities'] = $options['response_modalities'] ?? ['text', 'image'];
            $options['response_format'] = $options['response_format'] ?? $this->buildImageResponseFormat($options);

            $input = $this->buildImageInteractionInput($prompt, $options);
            $response = $this->interact($input, $options);

            if ($options['raw'] ?? false) {
                return $response;
            }

            return [
                'text' => $response['text'] ?? '',
                'image_url' => $response['image'] ?? null,
                'images' => $response['images'] ?? [],
                'interaction_id' => $response['interaction_id'] ?? null,
                'usage' => $response['usage'] ?? null,
                'raw' => $response['raw'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Gemini API Error (Image): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process an image with a text prompt using the Interactions API.
     *
     * @param string $prompt The text prompt to analyze the image.
     * @param mixed $image The image file, path, base64 string, data URI, or binary content.
     * @param array $options Additional options for the API.
     * @return array|string The response data or extracted text.
     */
    public function processImageText(string $prompt, $image, array $options = [])
    {
        try {
            $options['model'] = $options['model'] ?? $this->model('vision', 'gemini-3.5-flash');

            $input = [
                ['type' => 'text', 'text' => $prompt],
                $this->prepareImageContent(
                    $image,
                    $options['mime_type'] ?? null,
                    $options['resolution'] ?? null
                ),
            ];

            $response = $this->interact($input, $options);

            return $options['raw'] ?? false
                ? $response
                : ($response['text'] ?? '');
        } catch (\Exception $e) {
            Log::error('Gemini API Error (Vision): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate an embedding vector for text content.
     *
     * @param mixed $content Text or a Gemini Content object.
     * @param array $options Additional options for the API.
     * @return array
     */
    public function embed($content, array $options = [])
    {
        try {
            $model = $options['model'] ?? $this->model('embedding', 'gemini-embedding-2');
            $url = "{$this->baseUrl}/models/{$model}:embedContent";

            $payload = [
                'content' => $this->prepareGenerateContentContent($content),
            ];

            if (!empty($options['embedContentConfig'])) {
                $payload['embedContentConfig'] = $options['embedContentConfig'];
            }

            if (!empty($options['embed_content_config'])) {
                $payload['embedContentConfig'] = $options['embed_content_config'];
            }

            foreach (['taskType', 'title', 'outputDimensionality'] as $field) {
                if (array_key_exists($field, $options)) {
                    $payload[$field] = $options[$field];
                }
            }

            foreach (['task_type', 'output_dimensionality'] as $field) {
                if (array_key_exists($field, $options)) {
                    $payload[$this->toCamelCase($field)] = $options[$field];
                }
            }

            $response = Http::withHeaders($this->headers())->post($url, $payload);
            $this->validateResponse($response);
            $data = $response->json();

            return $options['raw'] ?? false
                ? $data
                : ($data['embedding']['values'] ?? []);
        } catch (\Exception $e) {
            Log::error('Gemini API Error (Embedding): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Count tokens for content before sending a generation request.
     *
     * @param mixed $content Text, contents array, or a full generateContentRequest.
     * @param array $options Additional options for the API.
     * @return array
     */
    public function countTokens($content, array $options = [])
    {
        try {
            $model = $options['model'] ?? $this->model('text', 'gemini-3.5-flash');
            $url = "{$this->baseUrl}/models/{$model}:countTokens";

            if (!empty($options['generateContentRequest'])) {
                $payload = ['generateContentRequest' => $options['generateContentRequest']];
            } elseif (!empty($options['generate_content_request'])) {
                $payload = ['generateContentRequest' => $options['generate_content_request']];
            } else {
                $payload = ['contents' => $this->prepareGenerateContentContents($content)];
            }

            $response = Http::withHeaders($this->headers())->post($url, $payload);
            $this->validateResponse($response);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Gemini API Error (Tokens): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate the API response.
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
     * Extract text content from a generateContent API response.
     *
     * @param array $data The response data.
     * @return string The extracted text.
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
     * Extract image content from a generateContent API response.
     *
     * @param array $data The response data.
     * @return array The image data.
     */
    protected function extractImageContent(array $data)
    {
        $result = [
            'text' => '',
            'image_url' => null,
            'images' => [],
        ];

        if (empty($data['candidates'][0]['content']['parts'])) {
            return $result;
        }

        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $result['text'] .= $part['text'];
            }

            $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;

            if (!empty($inlineData['data'])) {
                $imageUrl = $this->toDataUrl(
                    $inlineData['data'],
                    $inlineData['mimeType'] ?? $inlineData['mime_type'] ?? 'image/png'
                );

                $result['images'][] = $imageUrl;
                $result['image_url'] = $result['image_url'] ?? $imageUrl;
            }
        }

        return $result;
    }

    /**
     * Format an Interactions API response with Laravel-friendly helpers.
     *
     * @param array $data The raw interaction response.
     * @return array
     */
    protected function formatInteractionResponse(array $data)
    {
        $text = '';
        $images = [];

        foreach ($data['steps'] ?? [] as $step) {
            foreach ($step['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'text' && isset($content['text'])) {
                    $text .= $content['text'];
                }

                if (($content['type'] ?? null) === 'image') {
                    $image = $this->extractInteractionImage($content);

                    if ($image !== null) {
                        $images[] = $image;
                    }
                }
            }
        }

        return [
            'interaction_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
            'model' => $data['model'] ?? $data['agent'] ?? null,
            'text' => $text,
            'image' => $images[0] ?? null,
            'images' => $images,
            'steps' => $data['steps'] ?? [],
            'usage' => $data['usage'] ?? null,
            'raw' => $data,
        ];
    }

    /**
     * Prepare image for legacy generateContent API submission.
     *
     * @param mixed $image
     * @return string Base64 encoded image
     */
    protected function prepareImage($image)
    {
        return $this->prepareImageData($image)['data'];
    }

    /**
     * Prepare a content block for the Interactions API.
     *
     * @param mixed $input
     * @return mixed
     */
    protected function prepareInteractionInput($input)
    {
        return $input;
    }

    /**
     * Copy supported Interactions options onto the API payload.
     *
     * @param array $payload
     * @param array $options
     * @return void
     */
    protected function applyInteractionOptions(array &$payload, array $options)
    {
        $fields = [
            'system_instruction',
            'tools',
            'response_format',
            'stream',
            'store',
            'background',
            'generation_config',
            'agent_config',
            'environment',
            'previous_interaction_id',
            'response_modalities',
            'service_tier',
            'webhook_config',
            'cached_content',
            'safety_settings',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $options)) {
                $payload[$field] = $options[$field];
            }
        }

        $aliases = [
            'systemInstruction' => 'system_instruction',
            'responseFormat' => 'response_format',
            'generationConfig' => 'generation_config',
            'agentConfig' => 'agent_config',
            'previousInteractionId' => 'previous_interaction_id',
            'responseModalities' => 'response_modalities',
            'serviceTier' => 'service_tier',
            'webhookConfig' => 'webhook_config',
            'cachedContent' => 'cached_content',
            'safetySettings' => 'safety_settings',
        ];

        foreach ($aliases as $optionKey => $payloadKey) {
            if (!array_key_exists($payloadKey, $payload) && array_key_exists($optionKey, $options)) {
                $payload[$payloadKey] = $options[$optionKey];
            }
        }
    }

    /**
     * Build input for image generation and editing.
     *
     * @param string $prompt
     * @param array $options
     * @return array
     */
    protected function buildImageInteractionInput(string $prompt, array $options)
    {
        $input = [
            ['type' => 'text', 'text' => $prompt],
        ];

        foreach ($this->collectReferenceImages($options) as $image) {
            $input[] = $this->prepareImageContent(
                $image,
                $options['mime_type'] ?? null,
                $options['resolution'] ?? null
            );
        }

        return $input;
    }

    /**
     * Build response_format for text plus image output.
     *
     * @param array $options
     * @return array
     */
    protected function buildImageResponseFormat(array $options)
    {
        $imageFormat = [
            'type' => 'image',
            'mime_type' => $options['output_mime_type'] ?? $options['mime_type'] ?? 'image/png',
        ];

        foreach (['delivery', 'aspect_ratio', 'image_size'] as $field) {
            if (array_key_exists($field, $options)) {
                $imageFormat[$field] = $options[$field];
            }
        }

        return [
            ['type' => 'text'],
            $imageFormat,
        ];
    }

    /**
     * Collect image references from common option names.
     *
     * @param array $options
     * @return array
     */
    protected function collectReferenceImages(array $options)
    {
        $images = [];

        foreach (['image', 'images', 'reference_images', 'references'] as $key) {
            if (!array_key_exists($key, $options)) {
                continue;
            }

            if (is_array($options[$key]) && $this->isList($options[$key])) {
                $images = array_merge($images, $options[$key]);
            } else {
                $images[] = $options[$key];
            }
        }

        return $images;
    }

    /**
     * Prepare an image content block for the Interactions API.
     *
     * @param mixed $image
     * @param string|null $mimeType
     * @param string|null $resolution
     * @return array
     */
    protected function prepareImageContent($image, ?string $mimeType = null, ?string $resolution = null)
    {
        if (is_array($image) && ($image['type'] ?? null) === 'image') {
            return $image;
        }

        $imageData = $this->prepareImageData($image, $mimeType);

        $content = [
            'type' => 'image',
            'data' => $imageData['data'],
            'mime_type' => $imageData['mime_type'],
        ];

        if ($resolution !== null) {
            $content['resolution'] = $resolution;
        }

        return $content;
    }

    /**
     * Read image data from a path, uploaded file, data URI, base64 string, or binary string.
     *
     * @param mixed $image
     * @param string|null $mimeType
     * @return array
     */
    protected function prepareImageData($image, ?string $mimeType = null)
    {
        if (is_string($image) && preg_match('/^data:([^;]+);base64,(.*)$/s', $image, $matches)) {
            return [
                'data' => preg_replace('/\s+/', '', $matches[2]),
                'mime_type' => $mimeType ?? $matches[1],
            ];
        }

        if (is_string($image) && $this->isReadableFilePath($image)) {
            return [
                'data' => base64_encode(file_get_contents($image)),
                'mime_type' => $mimeType ?? $this->detectMimeType($image) ?? 'image/jpeg',
            ];
        }

        if (is_object($image) && method_exists($image, 'getRealPath')) {
            $path = $image->getRealPath();

            return [
                'data' => base64_encode(file_get_contents($path)),
                'mime_type' => $mimeType ?? $this->detectUploadedFileMimeType($image, $path) ?? 'image/jpeg',
            ];
        }

        if (is_string($image) && $this->looksLikeBase64($image)) {
            return [
                'data' => preg_replace('/\s+/', '', $image),
                'mime_type' => $mimeType ?? 'image/jpeg',
            ];
        }

        if (is_string($image)) {
            return [
                'data' => base64_encode($image),
                'mime_type' => $mimeType ?? 'image/jpeg',
            ];
        }

        throw new \InvalidArgumentException('Invalid image format provided.');
    }

    /**
     * Extract a data URL or URI from an Interactions image content block.
     *
     * @param array $content
     * @return string|null
     */
    protected function extractInteractionImage(array $content)
    {
        if (!empty($content['uri'])) {
            return $content['uri'];
        }

        if (!empty($content['data'])) {
            return $this->toDataUrl($content['data'], $content['mime_type'] ?? 'image/png');
        }

        return null;
    }

    /**
     * Prepare a Content object for generateContent-like APIs.
     *
     * @param mixed $content
     * @return array
     */
    protected function prepareGenerateContentContent($content)
    {
        if (is_string($content)) {
            return [
                'parts' => [
                    ['text' => $content],
                ],
            ];
        }

        if (is_array($content) && isset($content['parts'])) {
            return $content;
        }

        if (is_array($content) && ($content['type'] ?? null) !== null) {
            return [
                'parts' => $this->interactionBlocksToGenerateContentParts([$content]),
            ];
        }

        if (is_array($content) && $this->isList($content) && isset($content[0]['type'])) {
            return [
                'parts' => $this->interactionBlocksToGenerateContentParts($content),
            ];
        }

        return $content;
    }

    /**
     * Prepare a contents array for countTokens.
     *
     * @param mixed $content
     * @return array
     */
    protected function prepareGenerateContentContents($content)
    {
        if (is_array($content) && $this->isList($content) && isset($content[0]['parts'])) {
            return $content;
        }

        return [
            $this->prepareGenerateContentContent($content),
        ];
    }

    /**
     * Convert Interactions content blocks to generateContent parts.
     *
     * @param array $blocks
     * @return array
     */
    protected function interactionBlocksToGenerateContentParts(array $blocks)
    {
        $parts = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $parts[] = ['text' => $block['text'] ?? ''];
            }

            if (($block['type'] ?? null) === 'image' && !empty($block['data'])) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $block['mime_type'] ?? 'image/jpeg',
                        'data' => $block['data'],
                    ],
                ];
            }

            if (($block['type'] ?? null) === 'image' && !empty($block['uri'])) {
                $parts[] = [
                    'file_data' => [
                        'mime_type' => $block['mime_type'] ?? 'image/jpeg',
                        'file_uri' => $block['uri'],
                    ],
                ];
            }
        }

        return $parts;
    }

    /**
     * Build request headers for Gemini API authentication.
     *
     * @return array
     */
    protected function headers()
    {
        return [
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $this->apiKey,
        ];
    }

    /**
     * Resolve a configured model.
     *
     * @param string $type
     * @param string $fallback
     * @return string
     */
    protected function model(string $type, string $fallback)
    {
        return $this->models[$type] ?? $fallback;
    }

    /**
     * Convert base64 image data to a data URL.
     *
     * @param string $data
     * @param string $mimeType
     * @return string
     */
    protected function toDataUrl(string $data, string $mimeType)
    {
        if (strpos($data, 'data:') === 0) {
            return $data;
        }

        return "data:{$mimeType};base64,{$data}";
    }

    /**
     * Determine whether an array is a list without requiring PHP 8.1 array_is_list.
     *
     * @param array $array
     * @return bool
     */
    protected function isList(array $array)
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Avoid passing large binary strings to filesystem functions.
     *
     * @param string $value
     * @return bool
     */
    protected function isReadableFilePath(string $value)
    {
        if ($value === '' || strlen($value) > 4096 || strpos($value, "\0") !== false) {
            return false;
        }

        return is_file($value) && is_readable($value);
    }

    /**
     * Detect a file MIME type when PHP's fileinfo support is available.
     *
     * @param string $path
     * @return string|null
     */
    protected function detectMimeType(string $path)
    {
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($path);

            return $mimeType !== false ? $mimeType : null;
        }

        return null;
    }

    /**
     * Detect an uploaded file MIME type.
     *
     * @param object $file
     * @param string $path
     * @return string|null
     */
    protected function detectUploadedFileMimeType($file, string $path)
    {
        if (method_exists($file, 'getMimeType')) {
            return $file->getMimeType();
        }

        return $this->detectMimeType($path);
    }

    /**
     * Check whether a string is probably base64 encoded.
     *
     * @param string $value
     * @return bool
     */
    protected function looksLikeBase64(string $value)
    {
        $value = preg_replace('/\s+/', '', $value);

        if ($value === '' || strlen($value) % 4 !== 0) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $value);
    }

    /**
     * Convert a snake_case option name to camelCase.
     *
     * @param string $value
     * @return string
     */
    protected function toCamelCase(string $value)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
