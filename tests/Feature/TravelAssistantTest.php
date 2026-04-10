<?php

namespace Tests\Feature;

use App\Ai\Agents\TravelAssistant;
use App\Ai\Tools\GetAttraction;
use App\Ai\Tools\GetPreferences;
use App\Ai\Tools\GetWeather;
use App\Ai\Tools\SavePreference;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class TravelAssistantTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_travel_assistant_has_correct_tools(): void
    {
        $agent = new TravelAssistant(userId: 1);

        $tools = iterator_to_array($agent->tools());

        $this->assertCount(4, $tools);
        $this->assertInstanceOf(GetWeather::class, $tools[0]);
        $this->assertInstanceOf(GetAttraction::class, $tools[1]);
        $this->assertInstanceOf(SavePreference::class, $tools[2]);
        $this->assertInstanceOf(GetPreferences::class, $tools[3]);
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

    public function test_save_preference_tool_persists_to_database(): void
    {
        $user = User::factory()->create();
        $tool = new SavePreference($user->id);

        $result = $tool->handle(new Request([
            'key' => 'favorite_type',
            'value' => '历史文化',
        ]));

        $this->assertStringContainsString('已保存', $result);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => 'favorite_type',
            'value' => '历史文化',
        ]);
    }

    public function test_save_preference_tool_appends_to_existing(): void
    {
        $user = User::factory()->create();
        UserPreference::create(['user_id' => $user->id, 'key' => 'favorite_type', 'value' => '历史文化']);

        $tool = new SavePreference($user->id);
        $tool->handle(new Request([
            'key' => 'favorite_type',
            'value' => '自然风光',
        ]));

        $this->assertDatabaseCount('user_preferences', 1);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => 'favorite_type',
            'value' => '历史文化, 自然风光',
        ]);
    }

    public function test_save_preference_tool_skips_duplicate_value(): void
    {
        $user = User::factory()->create();
        UserPreference::create(['user_id' => $user->id, 'key' => 'favorite_type', 'value' => '历史文化']);

        $tool = new SavePreference($user->id);
        $result = (string) $tool->handle(new Request([
            'key' => 'favorite_type',
            'value' => '历史文化',
        ]));

        $this->assertStringContainsString('已存在', $result);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => 'favorite_type',
            'value' => '历史文化',
        ]);
    }

    public function test_get_preferences_tool_returns_saved_preferences(): void
    {
        $user = User::factory()->create();
        UserPreference::create(['user_id' => $user->id, 'key' => 'favorite_type', 'value' => '历史文化']);
        UserPreference::create(['user_id' => $user->id, 'key' => 'budget', 'value' => '500元以内']);

        $tool = new GetPreferences($user->id);
        $result = (string) $tool->handle(new Request([]));

        $this->assertStringContainsString('历史文化', $result);
        $this->assertStringContainsString('500元以内', $result);
    }

    public function test_get_preferences_tool_returns_empty_message(): void
    {
        $user = User::factory()->create();

        $tool = new GetPreferences($user->id);
        $result = (string) $tool->handle(new Request([]));

        $this->assertStringContainsString('暂无', $result);
    }

    public function test_get_weather_tool_has_correct_description(): void
    {
        $tool = new GetWeather;

        $this->assertStringContainsString('weather', (string) $tool->description());
    }

    public function test_get_attraction_tool_has_correct_description(): void
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
