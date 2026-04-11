# Multi-Agent Paradigm Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Combine ReAct, Plan-and-Solve, and Reflection paradigms into the TravelAssistant system using a multi-agent architecture with RouterAgent, PlannerAgent, ReviewerAgent, and a mode-aware TravelAssistant.

**Architecture:** A Command layer orchestrates 4 agents via deterministic if/else logic. RouterAgent classifies complexity (simple/medium/complex), PlannerAgent generates plans for complex tasks, TravelAssistant executes with mode-dependent instructions, and ReviewerAgent performs fact-based quality checks with scoped retry.

**Tech Stack:** Laravel 13, Laravel AI package, PHPUnit 12, groq provider with glm-5.1 model

---

## File Structure

| File | Action | Responsibility |
|------|--------|---------------|
| `app/Ai/Agents/RouterAgent.php` | Create | Classify task complexity → simple/medium/complex |
| `app/Ai/Agents/PlannerAgent.php` | Create | Decompose complex tasks into step-by-step plans |
| `app/Ai/Agents/ReviewerAgent.php` | Create | Fact-based quality check with 3 verification tools |
| `app/Ai/Agents/TravelAssistant.php` | Modify | Add `mode` param, 3 instruction sets |
| `app/Console/Commands/TravelAssistantCommand.php` | Modify | Orchestration logic in `singlePrompt()` |
| `tests/Feature/TravelAssistantTest.php` | Modify | Tests for all new agents and orchestration |

---

### Task 1: RouterAgent — Test and Implement

**Files:**
- Create: `app/Ai/Agents/RouterAgent.php`
- Test: `tests/Feature/TravelAssistantTest.php`

- [ ] **Step 1: Write the failing tests**

Add these tests to `tests/Feature/TravelAssistantTest.php`:

```php
public function test_router_agent_has_no_tools(): void
{
    $agent = new \App\Ai\Agents\RouterAgent;

    $this->assertFalse($agent instanceof \Laravel\Ai\Contracts\HasTools);
}

public function test_router_agent_has_instructions(): void
{
    $agent = new \App\Ai\Agents\RouterAgent;

    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString('simple', $instructions);
    $this->assertStringContainsString('medium', $instructions);
    $this->assertStringContainsString('complex', $instructions);
}

public function test_router_agent_classifies_simple_query(): void
{
    \App\Ai\Agents\RouterAgent::fake(['simple']);

    $response = (new \App\Ai\Agents\RouterAgent)->prompt('北京今天天气');

    $this->assertEquals('simple', trim($response->text));
}

public function test_router_agent_classifies_medium_query(): void
{
    \App\Ai\Agents\RouterAgent::fake(['medium']);

    $response = (new \App\Ai\Agents\RouterAgent)->prompt('北京天气怎么样，推荐景点');

    $this->assertEquals('medium', trim($response->text));
}

public function test_router_agent_classifies_complex_query(): void
{
    \App\Ai\Agents\RouterAgent::fake(['complex']);

    $response = (new \App\Ai\Agents\RouterAgent)->prompt('帮我规划3天北京深度游');

    $this->assertEquals('complex', trim($response->text));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact --filter=test_router_agent`
Expected: FAIL — class `RouterAgent` not found

- [ ] **Step 3: Create RouterAgent using artisan**

Run: `vendor/bin/sail artisan make:agent RouterAgent --no-interaction`

- [ ] **Step 4: Implement RouterAgent**

Replace the contents of `app/Ai/Agents/RouterAgent.php` with:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('groq')]
#[Model('glm-5.1')]
#[Temperature(0)]
class RouterAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        你是一个任务复杂度分类器。根据用户的旅行问题，判断复杂度并只返回一个词：

        - simple：单一信息查询（查天气、查门票、查单个景点）
        - medium：需要综合多个信息源推荐（推荐景点、比较方案）
        - complex：涉及多天/多城市/完整行程规划

        只输出 simple、medium 或 complex，不要输出任何其他内容。
        PROMPT;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact --filter=test_router_agent`
Expected: All 5 router tests PASS

- [ ] **Step 6: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Ai/Agents/RouterAgent.php tests/Feature/TravelAssistantTest.php
git commit -m "Add RouterAgent for task complexity classification"
```

---

### Task 2: PlannerAgent — Test and Implement

**Files:**
- Create: `app/Ai/Agents/PlannerAgent.php`
- Test: `tests/Feature/TravelAssistantTest.php`

- [ ] **Step 1: Write the failing tests**

Add these tests to `tests/Feature/TravelAssistantTest.php`:

```php
public function test_planner_agent_has_no_tools(): void
{
    $agent = new \App\Ai\Agents\PlannerAgent;

    $this->assertFalse($agent instanceof \Laravel\Ai\Contracts\HasTools);
}

public function test_planner_agent_has_instructions(): void
{
    $agent = new \App\Ai\Agents\PlannerAgent;

    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString('规划', $instructions);
    $this->assertStringContainsString('步骤', $instructions);
}

public function test_planner_agent_generates_plan(): void
{
    \App\Ai\Agents\PlannerAgent::fake([
        "1. 查询北京未来3天天气\n2. 获取用户旅行偏好\n3. 推荐Day1景点\n4. 推荐Day2景点\n5. 推荐Day3景点\n6. 检查门票可用性\n7. 整合成完整行程表",
    ]);

    $response = (new \App\Ai\Agents\PlannerAgent)->prompt('帮我规划3天北京深度游');

    $this->assertStringContainsString('天气', $response->text);
    $this->assertStringContainsString('Day1', $response->text);

    \App\Ai\Agents\PlannerAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '3天'));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact --filter=test_planner_agent`
Expected: FAIL — class `PlannerAgent` not found

- [ ] **Step 3: Create PlannerAgent using artisan**

Run: `vendor/bin/sail artisan make:agent PlannerAgent --no-interaction`

- [ ] **Step 4: Implement PlannerAgent**

Replace the contents of `app/Ai/Agents/PlannerAgent.php` with:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('groq')]
#[Model('glm-5.1')]
#[Temperature(0.3)]
class PlannerAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        你是一个旅行规划专家。你的任务是把用户的复杂旅行需求拆解成分步计划。

        规则：
        - 每个步骤必须是一个独立的、可执行的子任务
        - 按逻辑顺序排列
        - 只输出计划，不要执行

        输出格式（严格遵循）：
        1. [具体子任务描述]
        2. [具体子任务描述]
        ...
        PROMPT;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact --filter=test_planner_agent`
Expected: All 3 planner tests PASS

- [ ] **Step 6: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Ai/Agents/PlannerAgent.php tests/Feature/TravelAssistantTest.php
git commit -m "Add PlannerAgent for complex task decomposition"
```

---

### Task 3: ReviewerAgent — Test and Implement

**Files:**
- Create: `app/Ai/Agents/ReviewerAgent.php`
- Test: `tests/Feature/TravelAssistantTest.php`

- [ ] **Step 1: Write the failing tests**

Add these tests to `tests/Feature/TravelAssistantTest.php`:

```php
public function test_reviewer_agent_has_correct_tools(): void
{
    $agent = new \App\Ai\Agents\ReviewerAgent(userId: 1);

    $tools = iterator_to_array($agent->tools());

    $this->assertCount(3, $tools);
    $this->assertInstanceOf(\App\Ai\Tools\CheckTicketAvailability::class, $tools[0]);
    $this->assertInstanceOf(\App\Ai\Tools\GetWeather::class, $tools[1]);
    $this->assertInstanceOf(\App\Ai\Tools\GetPreferences::class, $tools[2]);
}

public function test_reviewer_agent_has_instructions(): void
{
    $agent = new \App\Ai\Agents\ReviewerAgent(userId: 1);

    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString('质量检查', $instructions);
    $this->assertStringContainsString('无需改进', $instructions);
    $this->assertStringContainsString('门票', $instructions);
}

public function test_reviewer_agent_does_not_have_get_attraction_tool(): void
{
    $agent = new \App\Ai\Agents\ReviewerAgent(userId: 1);

    $tools = iterator_to_array($agent->tools());
    $toolClasses = array_map(fn ($tool) => get_class($tool), $tools);

    $this->assertNotContains(\App\Ai\Tools\GetAttraction::class, $toolClasses);
    $this->assertNotContains(\App\Ai\Tools\SavePreference::class, $toolClasses);
    $this->assertNotContains(\App\Ai\Tools\TrackRejection::class, $toolClasses);
}

public function test_reviewer_agent_can_approve_result(): void
{
    \App\Ai\Agents\ReviewerAgent::fake(['无需改进']);

    $response = (new \App\Ai\Agents\ReviewerAgent(userId: 1))->prompt('请检查以下旅行推荐结果：推荐故宫');

    $this->assertStringContainsString('无需改进', $response->text);
}

public function test_reviewer_agent_can_reject_result(): void
{
    \App\Ai\Agents\ReviewerAgent::fake(['[问题1]: 故宫门票已售罄（事实：今日无余票）']);

    $response = (new \App\Ai\Agents\ReviewerAgent(userId: 1))->prompt('请检查以下旅行推荐结果：推荐故宫');

    $this->assertStringContainsString('问题', $response->text);
    $this->assertStringNotContainsString('无需改进', $response->text);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact --filter=test_reviewer_agent`
Expected: FAIL — class `ReviewerAgent` not found

- [ ] **Step 3: Create ReviewerAgent using artisan**

Run: `vendor/bin/sail artisan make:agent ReviewerAgent --no-interaction`

- [ ] **Step 4: Implement ReviewerAgent**

Replace the contents of `app/Ai/Agents/ReviewerAgent.php` with:

```php
<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CheckTicketAvailability;
use App\Ai\Tools\GetPreferences;
use App\Ai\Tools\GetWeather;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('groq')]
#[Model('glm-5.1')]
#[MaxSteps(6)]
#[Temperature(0)]
class ReviewerAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(private int $userId = 0) {}

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        你是一个旅行推荐质量检查员。你会收到一份旅行推荐结果，请检查以下3个硬性问题：

        1. 推荐的景点门票是否售罄？（使用 CheckTicketAvailability 工具验证）
        2. 推荐是否与当前天气明显矛盾？如暴雨推荐户外（使用 GetWeather 工具验证）
        3. 推荐是否与用户明确偏好冲突？（使用 GetPreferences 工具验证）

        检查规则：
        - 只检查以上3个问题，不要提出主观改进建议
        - 如果没有问题，只回答"无需改进"
        - 如果有问题，列出具体问题和事实依据，格式：
          [问题1]: xxx（事实：xxx）
          [问题2]: xxx（事实：xxx）
        PROMPT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new CheckTicketAvailability,
            new GetWeather,
            new GetPreferences($this->userId),
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact --filter=test_reviewer_agent`
Expected: All 5 reviewer tests PASS

- [ ] **Step 6: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Ai/Agents/ReviewerAgent.php tests/Feature/TravelAssistantTest.php
git commit -m "Add ReviewerAgent for fact-based quality verification"
```

---

### Task 4: TravelAssistant Mode Support — Test and Implement

**Files:**
- Modify: `app/Ai/Agents/TravelAssistant.php`
- Test: `tests/Feature/TravelAssistantTest.php`

- [ ] **Step 1: Write the failing tests**

Add these tests to `tests/Feature/TravelAssistantTest.php`:

```php
public function test_travel_assistant_simple_mode_instructions(): void
{
    $agent = new TravelAssistant(userId: 1, mode: 'simple');

    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString('直接', $instructions);
    $this->assertStringNotContainsString('思考过程', $instructions);
}

public function test_travel_assistant_medium_mode_instructions(): void
{
    $agent = new TravelAssistant(userId: 1, mode: 'medium');

    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString('思考过程', $instructions);
    $this->assertStringContainsString('每次只执行一个工具调用', $instructions);
}

public function test_travel_assistant_complex_mode_instructions(): void
{
    $agent = new TravelAssistant(userId: 1, mode: 'complex');

    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString('计划', $instructions);
    $this->assertStringContainsString('第几步', $instructions);
}

public function test_travel_assistant_default_mode_is_simple(): void
{
    $agent = new TravelAssistant(userId: 1);

    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString('直接', $instructions);
}

public function test_travel_assistant_tools_unchanged_across_modes(): void
{
    $simpleTools = iterator_to_array((new TravelAssistant(userId: 1, mode: 'simple'))->tools());
    $mediumTools = iterator_to_array((new TravelAssistant(userId: 1, mode: 'medium'))->tools());
    $complexTools = iterator_to_array((new TravelAssistant(userId: 1, mode: 'complex'))->tools());

    $this->assertCount(6, $simpleTools);
    $this->assertCount(6, $mediumTools);
    $this->assertCount(6, $complexTools);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact --filter=test_travel_assistant_simple_mode`
Expected: FAIL — constructor does not accept `mode` parameter

- [ ] **Step 3: Modify TravelAssistant**

Update `app/Ai/Agents/TravelAssistant.php`. Change the constructor and `instructions()` method:

```php
public function __construct(
    private int $userId = 0,
    private string $mode = 'simple',
) {}

public function instructions(): Stringable|string
{
    return match ($this->mode) {
        'medium' => $this->reactInstructions(),
        'complex' => $this->planReactInstructions(),
        default => $this->simpleInstructions(),
    };
}

private function simpleInstructions(): string
{
    return <<<'PROMPT'
    你是一个智能旅行助手。直接使用工具回答用户问题，简洁明了。
    PROMPT;
}

private function reactInstructions(): string
{
    return <<<'PROMPT'
    你是一个智能旅行助手。
    每次调用工具之前，你必须先输出你的思考过程，说明：你现在掌握了什么信息、还缺什么信息、为什么要调用这个工具。
    每次只执行一个工具调用。
    收集到足够信息后给出最终答案。
    PROMPT;
}

private function planReactInstructions(): string
{
    return <<<'PROMPT'
    你是一个智能旅行助手。你会收到一份分步计划。
    请严格按照计划的步骤逐步执行，每步：
    1. 说明当前执行计划的第几步
    2. 说出思考过程
    3. 调用工具
    不要跳步，不要合并步骤。全部执行完后输出完整的最终答案。
    PROMPT;
}
```

- [ ] **Step 4: Update existing test for new constructor**

In `tests/Feature/TravelAssistantTest.php`, update `test_travel_assistant_has_instructions`:

```php
public function test_travel_assistant_has_instructions(): void
{
    $agent = new TravelAssistant(mode: 'medium');

    $instructions = (string) $agent->instructions();

    $this->assertStringContainsString('智能旅行助手', $instructions);
    $this->assertStringContainsString('思考过程', $instructions);
}
```

- [ ] **Step 5: Run all travel assistant tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact --filter=test_travel_assistant`
Expected: All travel assistant tests PASS (new mode tests + updated existing tests)

- [ ] **Step 6: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Ai/Agents/TravelAssistant.php tests/Feature/TravelAssistantTest.php
git commit -m "Add mode-dependent instructions to TravelAssistant (simple/medium/complex)"
```

---

### Task 5: Command Orchestration — Test and Implement

**Files:**
- Modify: `app/Console/Commands/TravelAssistantCommand.php`
- Test: `tests/Feature/TravelAssistantTest.php`

- [ ] **Step 1: Write the failing tests**

Add these tests to `tests/Feature/TravelAssistantTest.php`:

```php
public function test_command_routes_simple_query(): void
{
    \App\Ai\Agents\RouterAgent::fake(['simple']);
    TravelAssistant::fake(['北京今天晴天，25°C。']);

    $this->artisan('travel:ask', ['prompt' => '北京今天天气'])
        ->assertSuccessful();

    TravelAssistant::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '北京今天天气'));
}

public function test_command_routes_medium_query_with_review(): void
{
    \App\Ai\Agents\RouterAgent::fake(['medium']);
    TravelAssistant::fake(['推荐故宫，门票充足。']);
    \App\Ai\Agents\ReviewerAgent::fake(['无需改进']);

    $this->artisan('travel:ask', ['prompt' => '推荐北京景点'])
        ->assertSuccessful();
}

public function test_command_routes_complex_query_with_plan_and_review(): void
{
    \App\Ai\Agents\RouterAgent::fake(['complex']);
    \App\Ai\Agents\PlannerAgent::fake(["1. 查天气\n2. 查景点\n3. 查门票"]);
    TravelAssistant::fake(['3天行程：Day1 故宫，Day2 长城，Day3 颐和园。']);
    \App\Ai\Agents\ReviewerAgent::fake(['无需改进']);

    $this->artisan('travel:ask', ['prompt' => '规划3天北京行程'])
        ->assertSuccessful();

    \App\Ai\Agents\PlannerAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '3天'));
}

public function test_command_retries_when_review_finds_issues(): void
{
    \App\Ai\Agents\RouterAgent::fake(['medium']);
    TravelAssistant::fake([
        '推荐故宫，非常值得一去。',
        '推荐国家博物馆，免费参观。',
    ]);
    \App\Ai\Agents\ReviewerAgent::fake([
        '[问题1]: 故宫门票已售罄（事实：今日无余票）',
        '无需改进',
    ]);

    $this->artisan('travel:ask', ['prompt' => '推荐北京景点'])
        ->assertSuccessful();
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact --filter=test_command_routes`
Expected: FAIL — command does not have routing logic yet

- [ ] **Step 3: Implement orchestration in TravelAssistantCommand**

Replace the `singlePrompt` method in `app/Console/Commands/TravelAssistantCommand.php`:

```php
private function singlePrompt(User $user): int
{
    $prompt = $this->argument('prompt')
        ?? $this->ask('What would you like to know?', '请查询北京天气并推荐合适的旅游景点。');

    // Step 1: Route — classify complexity
    $this->info('正在分析问题复杂度...');
    $level = trim(strtolower((new RouterAgent)->prompt($prompt)->text));

    if (! in_array($level, ['simple', 'medium', 'complex'])) {
        $level = 'medium';
    }

    $this->components->twoColumnDetail('复杂度', $level);

    // Step 2: Plan — complex only
    $executionPrompt = $prompt;

    if ($level === 'complex') {
        $this->info('正在生成计划...');
        $plan = (new PlannerAgent)->forUser($user)->prompt($prompt);
        $this->line($plan->text);
        $this->newLine();
        $executionPrompt = "请按照以下计划执行：\n{$plan->text}\n\n用户原始需求：{$prompt}";
    }

    // Step 3: Execute
    $this->info('正在执行...');
    $agent = new TravelAssistant($user->id, mode: $level);
    $response = $agent->forUser($user)->prompt($executionPrompt);
    $this->printResponse($response);

    // Step 4: Review — medium and complex only
    if ($level !== 'simple') {
        $response = $this->review($user, $agent, $response, $prompt);
    }

    return self::SUCCESS;
}

private function review(User $user, TravelAssistant $agent, mixed $response, string $originalPrompt): mixed
{
    $this->info('正在质量检查...');
    $reviewer = new ReviewerAgent($user->id);
    $review = $reviewer->forUser($user)->prompt(
        "请检查以下旅行推荐结果：\n{$response->text}\n\n用户原始需求：{$originalPrompt}"
    );

    if (str_contains($review->text, '无需改进')) {
        $this->info('✅ 质量检查通过');

        return $response;
    }

    // Retry once with feedback
    $this->warn('发现问题，正在修正...');
    $this->line($review->text);
    $this->newLine();

    $response = $agent->forUser($user)->prompt(
        "你之前的推荐存在以下问题：\n{$review->text}\n\n请修正后重新推荐。用户原始需求：{$originalPrompt}"
    );
    $this->printResponse($response);

    // Scoped recheck
    $this->info('正在复查...');
    $recheck = $reviewer->forUser($user)->prompt(
        "上一次检查发现以下问题：\n{$review->text}\n\n请只验证这些问题是否已修正：\n{$response->text}"
    );

    if (str_contains($recheck->text, '无需改进')) {
        $this->info('✅ 修正后验证通过');
    } else {
        $this->warn('⚠️ 部分问题仍未解决，建议自行确认门票等信息。');
    }

    return $response;
}
```

- [ ] **Step 4: Add use statements to TravelAssistantCommand**

Add at the top of `TravelAssistantCommand.php`:

```php
use App\Ai\Agents\PlannerAgent;
use App\Ai\Agents\ReviewerAgent;
use App\Ai\Agents\RouterAgent;
```

- [ ] **Step 5: Run all command tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact --filter=test_command_routes`
Expected: All 4 command routing tests PASS

- [ ] **Step 6: Also run the retry test**

Run: `vendor/bin/sail artisan test --compact --filter=test_command_retries`
Expected: PASS

- [ ] **Step 7: Run pint**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Console/Commands/TravelAssistantCommand.php tests/Feature/TravelAssistantTest.php
git commit -m "Add multi-agent orchestration with routing, planning, and review"
```

---

### Task 6: Full Test Suite and Manual Verification

**Files:**
- Test: `tests/Feature/TravelAssistantTest.php`

- [ ] **Step 1: Run the full test suite**

Run: `vendor/bin/sail artisan test --compact`
Expected: All tests PASS — no regressions

- [ ] **Step 2: Manual test — simple query**

Run: `vendor/bin/sail artisan travel:ask "北京今天天气"`

Expected output:
- Shows `复杂度: simple`
- Directly returns weather, no review step

- [ ] **Step 3: Manual test — medium query**

Run: `vendor/bin/sail artisan travel:ask "查上海天气推荐景点"`

Expected output:
- Shows `复杂度: medium`
- Shows thought process before each tool call (ReAct)
- Shows `✅ 质量检查通过` or review feedback

- [ ] **Step 4: Manual test — complex query**

Run: `vendor/bin/sail artisan travel:ask "帮我规划3天北京深度游，我喜欢历史文化"`

Expected output:
- Shows `复杂度: complex`
- Shows numbered plan from PlannerAgent
- Shows step-by-step execution referencing the plan
- Shows review result

- [ ] **Step 5: Run pint one final time**

Run: `vendor/bin/sail bin pint --dirty --format agent`

- [ ] **Step 6: Final commit if any formatting changes**

```bash
git add -A
git commit -m "Fix formatting after full test suite"
```
