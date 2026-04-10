<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

use function Laravel\Ai\agent;

class GetAttraction implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search and recommend tourist attractions for a given city based on the current weather conditions.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $city = $request['city'];
        $weather = $request['weather'];

        $response = agent(
            instructions: <<<'PROMPT'
            你是一位旅行专家。根据提供的城市和天气信息，推荐 2-3 个旅游景点。
            对每个景点，说明它为什么适合当前天气。
            使用与城市名称相同的语言回复。
            回答要简洁实用。
            PROMPT,
        )->prompt(
            "City: {$city}, Weather: {$weather}. Please recommend suitable tourist attractions.",
            provider: 'groq',
            model: 'glm-5.1',
        );

        $text = (string) $response;

        if (empty(trim($text))) {
            return "Sorry, could not find attraction recommendations for {$city} in {$weather} weather.";
        }

        return $text;
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
