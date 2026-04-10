<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetWeather implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Query real-time weather for a specified city, including temperature and weather conditions.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $city = $request['city'];

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->get("https://wttr.in/{$city}", ['format' => 'j1']);

        if ($response->failed()) {
            return "Error: Failed to query weather for {$city}.";
        }

        $data = $response->json();

        $condition = data_get($data, 'current_condition.0');

        if (! $condition) {
            return "Error: Unable to parse weather data for {$city}.";
        }

        $description = data_get($condition, 'weatherDesc.0.value', 'Unknown');
        $tempC = data_get($condition, 'temp_C', 'N/A');
        $humidity = data_get($condition, 'humidity', 'N/A');
        $windSpeed = data_get($condition, 'windspeedKmph', 'N/A');

        return "{$city} current weather: {$description}, temperature {$tempC}°C, humidity {$humidity}%, wind speed {$windSpeed}km/h.";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'city' => $schema->string()
                ->description('The city name to query weather for, e.g. Beijing, Shanghai, Tokyo')
                ->required(),
        ];
    }
}
