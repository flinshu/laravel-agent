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
            You are a travel expert. Based on the city and weather provided, recommend 2-3 tourist attractions.
            For each attraction, explain why it is suitable for the given weather.
            Reply in the same language as the city name.
            Be concise and practical.
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
