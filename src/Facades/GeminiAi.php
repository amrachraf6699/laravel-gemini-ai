<?php

namespace Amrachraf6699\LaravelGeminiAi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed generateText(string $prompt, array $options = [])
 * @method static mixed generateImage(string $prompt, array $options = [])
 * @method static mixed processImageText(string $prompt, mixed $image, array $options = [])
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