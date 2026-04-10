<?php

namespace Tests\Feature;

use App\Ai\Agents\TravelAssistant;
use App\Ai\Tools\GetAttraction;
use App\Ai\Tools\GetWeather;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TravelAssistantTest extends TestCase
{
    public function test_travel_assistant_has_correct_tools(): void
    {
        $agent = new TravelAssistant;

        $tools = iterator_to_array($agent->tools());

        $this->assertCount(2, $tools);
        $this->assertInstanceOf(GetWeather::class, $tools[0]);
        $this->assertInstanceOf(GetAttraction::class, $tools[1]);
    }

    public function test_travel_assistant_has_instructions(): void
    {
        $agent = new TravelAssistant;

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('智能旅行助手', $instructions);
        $this->assertStringContainsString('重要提示', $instructions);
    }

    public function test_travel_assistant_can_be_prompted_with_fake(): void
    {
        TravelAssistant::fake([
            'Today in Beijing the weather is sunny, 25°C. I recommend visiting the Summer Palace.',
        ]);

        $response = (new TravelAssistant)->prompt('What is the weather in Beijing? Recommend attractions.');

        $this->assertStringContainsString('Beijing', $response->text);

        TravelAssistant::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Beijing'));
    }

    public function test_get_weather_tool_has_correct_schema(): void
    {
        $tool = new GetWeather;

        $this->assertStringContainsString('weather', (string) $tool->description());
    }

    public function test_get_attraction_tool_has_correct_schema(): void
    {
        $tool = new GetAttraction;

        $this->assertStringContainsString('景点', (string) $tool->description());
    }

    public function test_travel_assistant_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('travel:ask', $commands);
    }
}
