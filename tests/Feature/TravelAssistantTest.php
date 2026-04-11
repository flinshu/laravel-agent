<?php

namespace Tests\Feature;

use App\Ai\Agents\PlannerAgent;
use App\Ai\Agents\ReviewerAgent;
use App\Ai\Agents\RouterAgent;
use App\Ai\Agents\TravelAssistant;
use App\Ai\Tools\CheckTicketAvailability;
use App\Ai\Tools\GetAttraction;
use App\Ai\Tools\GetPreferences;
use App\Ai\Tools\GetWeather;
use App\Ai\Tools\SavePreference;
use App\Ai\Tools\TrackRejection;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class TravelAssistantTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_travel_assistant_has_correct_tools(): void
    {
        $agent = new TravelAssistant(userId: 1);

        $tools = iterator_to_array($agent->tools());

        $this->assertCount(6, $tools);
        $this->assertInstanceOf(GetWeather::class, $tools[0]);
        $this->assertInstanceOf(GetAttraction::class, $tools[1]);
        $this->assertInstanceOf(CheckTicketAvailability::class, $tools[2]);
        $this->assertInstanceOf(SavePreference::class, $tools[3]);
        $this->assertInstanceOf(GetPreferences::class, $tools[4]);
        $this->assertInstanceOf(TrackRejection::class, $tools[5]);
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

    public function test_track_rejection_records_and_counts(): void
    {
        $user = User::factory()->create();
        $tool = new TrackRejection($user->id);

        $result1 = (string) $tool->handle(new Request([
            'attraction' => '故宫',
            'reason' => '人太多',
        ]));

        $this->assertStringContainsString('拒绝次数：1', $result1);
        $this->assertDatabaseHas('rejection_logs', [
            'user_id' => $user->id,
            'attraction' => '故宫',
            'reason' => '人太多',
        ]);
    }

    public function test_track_rejection_triggers_strategy_adjustment_at_3(): void
    {
        $user = User::factory()->create();
        $tool = new TrackRejection($user->id);

        $tool->handle(new Request(['attraction' => '故宫', 'reason' => '人太多']));
        $tool->handle(new Request(['attraction' => '长城', 'reason' => '太远']));
        $result = (string) $tool->handle(new Request(['attraction' => '天坛', 'reason' => '去过了']));

        $this->assertStringContainsString('连续拒绝', $result);
        $this->assertStringContainsString('调整策略', $result);
        $this->assertStringContainsString('故宫', $result);
        $this->assertStringContainsString('长城', $result);
        $this->assertStringContainsString('天坛', $result);
        $this->assertDatabaseCount('rejection_logs', 3);
    }

    public function test_check_ticket_availability_tool_has_correct_description(): void
    {
        $tool = new CheckTicketAvailability;

        $description = (string) $tool->description();

        $this->assertStringContainsString('门票', $description);
        $this->assertStringContainsString('售罄', $description);
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

    public function test_router_agent_has_no_tools(): void
    {
        $agent = new RouterAgent;

        $this->assertFalse($agent instanceof HasTools);
    }

    public function test_router_agent_has_instructions(): void
    {
        $agent = new RouterAgent;

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('simple', $instructions);
        $this->assertStringContainsString('medium', $instructions);
        $this->assertStringContainsString('complex', $instructions);
    }

    public function test_router_agent_classifies_simple_query(): void
    {
        RouterAgent::fake(['simple']);

        $response = (new RouterAgent)->prompt('北京今天天气');

        $this->assertEquals('simple', trim($response->text));
    }

    public function test_router_agent_classifies_medium_query(): void
    {
        RouterAgent::fake(['medium']);

        $response = (new RouterAgent)->prompt('北京天气怎么样，推荐景点');

        $this->assertEquals('medium', trim($response->text));
    }

    public function test_router_agent_classifies_complex_query(): void
    {
        RouterAgent::fake(['complex']);

        $response = (new RouterAgent)->prompt('帮我规划3天北京深度游');

        $this->assertEquals('complex', trim($response->text));
    }

    public function test_planner_agent_has_no_tools(): void
    {
        $agent = new PlannerAgent;

        $this->assertFalse($agent instanceof HasTools);
    }

    public function test_planner_agent_has_instructions(): void
    {
        $agent = new PlannerAgent;

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('规划', $instructions);
        $this->assertStringContainsString('步骤', $instructions);
    }

    public function test_planner_agent_generates_plan(): void
    {
        PlannerAgent::fake([
            "1. 查询北京未来3天天气\n2. 获取用户旅行偏好\n3. 推荐Day1景点\n4. 推荐Day2景点\n5. 推荐Day3景点\n6. 检查门票可用性\n7. 整合成完整行程表",
        ]);

        $response = (new PlannerAgent)->prompt('帮我规划3天北京深度游');

        $this->assertStringContainsString('天气', $response->text);
        $this->assertStringContainsString('Day1', $response->text);

        PlannerAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '3天'));
    }

    public function test_reviewer_agent_has_correct_tools(): void
    {
        $agent = new ReviewerAgent(userId: 1);

        $tools = iterator_to_array($agent->tools());

        $this->assertCount(3, $tools);
        $this->assertInstanceOf(CheckTicketAvailability::class, $tools[0]);
        $this->assertInstanceOf(GetWeather::class, $tools[1]);
        $this->assertInstanceOf(GetPreferences::class, $tools[2]);
    }

    public function test_reviewer_agent_has_instructions(): void
    {
        $agent = new ReviewerAgent(userId: 1);

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('质量检查', $instructions);
        $this->assertStringContainsString('无需改进', $instructions);
        $this->assertStringContainsString('门票', $instructions);
    }

    public function test_reviewer_agent_does_not_have_get_attraction_tool(): void
    {
        $agent = new ReviewerAgent(userId: 1);

        $tools = iterator_to_array($agent->tools());
        $toolClasses = array_map(fn ($tool) => get_class($tool), $tools);

        $this->assertNotContains(GetAttraction::class, $toolClasses);
        $this->assertNotContains(SavePreference::class, $toolClasses);
        $this->assertNotContains(TrackRejection::class, $toolClasses);
    }

    public function test_reviewer_agent_can_approve_result(): void
    {
        ReviewerAgent::fake(['无需改进']);

        $response = (new ReviewerAgent(userId: 1))->prompt('请检查以下旅行推荐结果：推荐故宫');

        $this->assertStringContainsString('无需改进', $response->text);
    }

    public function test_reviewer_agent_can_reject_result(): void
    {
        ReviewerAgent::fake(['[问题1]: 故宫门票已售罄（事实：今日无余票）']);

        $response = (new ReviewerAgent(userId: 1))->prompt('请检查以下旅行推荐结果：推荐故宫');

        $this->assertStringContainsString('问题', $response->text);
        $this->assertStringNotContainsString('无需改进', $response->text);
    }
}
