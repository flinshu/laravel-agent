<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetAttraction implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return '根据城市和天气搜索推荐的旅游景点。';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $city = $request['city'];
        $weather = $request['weather'];

        $apiKey = config('services.tavily.key');

        if (empty($apiKey)) {
            return '错误：未配置 TAVILY_API_KEY。';
        }

        $query = "'{$city}' 在'{$weather}'天气下最值得去的旅游景点推荐及理由";

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->withToken($apiKey, 'Bearer')
            ->post('https://api.tavily.com/search', [
                'query' => $query,
                'search_depth' => 'basic',
                'include_answer' => true,
            ]);

        if ($response->failed()) {
            return "错误：执行Tavily搜索时出现问题 - HTTP {$response->status()}";
        }

        $data = $response->json();

        if ($answer = data_get($data, 'answer')) {
            return $answer;
        }

        $results = data_get($data, 'results', []);

        if (empty($results)) {
            return '抱歉，没有找到相关的旅游景点推荐。';
        }

        $formatted = collect($results)
            ->map(fn (array $result) => "- {$result['title']}: {$result['content']}")
            ->implode("\n");

        return "根据搜索，为您找到以下信息：\n{$formatted}";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'city' => $schema->string()
                ->description('The city name to search attractions for')
                ->required(),
            'weather' => $schema->string()
                ->description('The current weather condition, e.g. Sunny, Rainy, Cloudy')
                ->required(),
        ];
    }
}
