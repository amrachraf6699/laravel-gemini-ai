<?php

namespace Amrachraf6699\LaravelGeminiAi\Tests;

use Amrachraf6699\LaravelGeminiAi\Facades\GeminiAi;
use Amrachraf6699\LaravelGeminiAi\Providers\GeminiAiServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class GeminiAiServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            GeminiAiServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'GeminiAi' => GeminiAi::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('gemini.api_key', 'test-key');
        $app['config']->set('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $app['config']->set('gemini.models', [
            'text' => 'gemini-3.5-flash',
            'image' => 'gemini-3.1-flash-image',
            'vision' => 'gemini-3.5-flash',
            'embedding' => 'gemini-embedding-2',
        ]);
    }

    public function test_interact_sends_text_payload_and_returns_helpers()
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'interaction-123',
                'model' => 'gemini-3.5-flash',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            ['type' => 'text', 'text' => 'Hello from Gemini'],
                        ],
                    ],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
            ]),
        ]);

        $response = GeminiAi::interact('Hello', [
            'system_instruction' => 'Be concise.',
            'generation_config' => ['temperature' => 0.2],
            'response_format' => ['type' => 'text'],
            'tools' => [GeminiAi::googleSearchTool()],
            'store' => true,
            'background' => false,
        ]);

        $this->assertSame('interaction-123', $response['interaction_id']);
        $this->assertSame('Hello from Gemini', $response['text']);
        $this->assertSame(['input_tokens' => 5, 'output_tokens' => 3], $response['usage']);
        $this->assertArrayHasKey('raw', $response);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/interactions', $request->url());
            $this->assertTrue($request->hasHeader('x-goog-api-key'));
            $this->assertSame(['test-key'], $request->header('x-goog-api-key'));
            $this->assertSame('gemini-3.5-flash', $request['model']);
            $this->assertSame('Hello', $request['input']);
            $this->assertSame('Be concise.', $request['system_instruction']);
            $this->assertSame(['temperature' => 0.2], $request['generation_config']);
            $this->assertSame([['type' => 'google_search']], $request['tools']);
            $this->assertTrue($request['store']);
            $this->assertFalse($request['background']);

            return true;
        });
    }

    public function test_generate_json_decodes_valid_json()
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'interaction-123',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            ['type' => 'text', 'text' => '{"customer_name":"Sarah","plan":"Pro"}'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = GeminiAi::generateJson('Extract the customer plan.', [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string'],
                'plan' => ['type' => 'string'],
            ],
            'required' => ['customer_name', 'plan'],
        ]);

        $this->assertSame(['customer_name' => 'Sarah', 'plan' => 'Pro'], $response['json']);
        $this->assertNull($response['json_error']);

        Http::assertSent(function (Request $request) {
            $this->assertSame('application/json', $request['response_format']['mime_type']);
            $this->assertSame('object', $request['response_format']['schema']['type']);

            return true;
        });
    }

    public function test_generate_json_returns_raw_text_on_invalid_json()
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'interaction-123',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            ['type' => 'text', 'text' => 'not json'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = GeminiAi::generateJson('Return JSON.', [
            'type' => 'object',
        ]);

        $this->assertNull($response['json']);
        $this->assertSame('not json', $response['text']);
        $this->assertNotNull($response['json_error']);
    }

    public function test_chat_sends_previous_interaction_id_and_store()
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'interaction-456',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            ['type' => 'text', 'text' => 'Shorter headline'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = GeminiAi::chat('Make it shorter.', 'interaction-123');

        $this->assertSame('interaction-456', $response['interaction_id']);
        $this->assertSame('Shorter headline', $response['text']);

        Http::assertSent(function (Request $request) {
            $this->assertSame('interaction-123', $request['previous_interaction_id']);
            $this->assertTrue($request['store']);

            return true;
        });
    }

    public function test_image_generation_extracts_image_and_sends_reference_image()
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'interaction-image',
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            ['type' => 'text', 'text' => 'Generated'],
                            ['type' => 'image', 'data' => 'aW1hZ2U=', 'mime_type' => 'image/png'],
                        ],
                    ],
                ],
                'usage' => ['input_tokens' => 10],
            ]),
        ]);

        $response = GeminiAi::generateImage('Edit the reference.', [
            'image' => 'data:image/png;base64,cmVmZXJlbmNl',
            'aspect_ratio' => '1:1',
            'image_size' => '1K',
        ]);

        $this->assertSame('Generated', $response['text']);
        $this->assertSame('data:image/png;base64,aW1hZ2U=', $response['image_url']);
        $this->assertSame(['input_tokens' => 10], $response['usage']);

        Http::assertSent(function (Request $request) {
            $this->assertSame('gemini-3.1-flash-image', $request['model']);
            $this->assertSame(['text', 'image'], $request['response_modalities']);
            $this->assertSame('Edit the reference.', $request['input'][0]['text']);
            $this->assertSame('image', $request['input'][1]['type']);
            $this->assertSame('image/png', $request['input'][1]['mime_type']);
            $this->assertSame('cmVmZXJlbmNl', $request['input'][1]['data']);
            $this->assertSame('1:1', $request['response_format'][1]['aspect_ratio']);
            $this->assertSame('1K', $request['response_format'][1]['image_size']);

            return true;
        });
    }

    public function test_tool_helpers_serialize_expected_types()
    {
        $this->assertSame(['type' => 'google_search'], GeminiAi::googleSearchTool());
        $this->assertSame(['type' => 'url_context'], GeminiAi::urlContextTool());
        $this->assertSame(['type' => 'code_execution'], GeminiAi::codeExecutionTool());
    }

    public function test_count_tokens_hits_count_tokens_endpoint()
    {
        Http::fake([
            '*' => Http::response(['totalTokens' => 12]),
        ]);

        $tokens = GeminiAi::countTokens('Count me.');

        $this->assertSame(12, $tokens['totalTokens']);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:countTokens', $request->url());
            $this->assertSame('Count me.', $request['contents'][0]['parts'][0]['text']);

            return true;
        });
    }

    public function test_embed_hits_embed_content_and_returns_values()
    {
        Http::fake([
            '*' => Http::response([
                'embedding' => [
                    'values' => [0.1, 0.2, 0.3],
                ],
            ]),
        ]);

        $vector = GeminiAi::embed('Semantic search input.', [
            'task_type' => 'RETRIEVAL_DOCUMENT',
            'output_dimensionality' => 3,
        ]);

        $this->assertSame([0.1, 0.2, 0.3], $vector);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent', $request->url());
            $this->assertSame('Semantic search input.', $request['content']['parts'][0]['text']);
            $this->assertSame('RETRIEVAL_DOCUMENT', $request['taskType']);
            $this->assertSame(3, $request['outputDimensionality']);

            return true;
        });
    }
}
