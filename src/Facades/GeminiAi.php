<?php

namespace Amrachraf6699\LaravelGeminiAi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array interact(mixed $input, array $options = [])
 * @method static array generateJson(string $prompt, array $schema, array $options = [])
 * @method static array chat(string $prompt, ?string $previousInteractionId = null, array $options = [])
 * @method static array googleSearchTool(array $options = [])
 * @method static array urlContextTool(array $options = [])
 * @method static array codeExecutionTool(array $options = [])
 * @method static mixed generateText(string $prompt, array $options = [])
 * @method static mixed generateImage(string $prompt, array $options = [])
 * @method static mixed processImageText(string $prompt, mixed $image, array $options = [])
 * @method static array embed(mixed $content, array $options = [])
 * @method static array countTokens(mixed $content, array $options = [])
 * 
 * @see \Amrachraf6699\LaravelGeminiAi\Services\GeminiAiService
 */
class GeminiAi extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'gemini-ai';
    }
}
