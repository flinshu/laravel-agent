# Multi-Agent Paradigm Design: ReAct + Plan-and-Solve + Reflection

## Summary

Combine three classic agent paradigms (ReAct, Plan-and-Solve, Reflection) in the TravelAssistant system by introducing a multi-agent architecture. A RouterAgent classifies task complexity, then the Command layer orchestrates the appropriate agents for that complexity level.

## Architecture

```
用户输入
  ↓
RouterAgent → simple ──→ TravelAssistant(simple) ──→ 直接返回
           → medium ──→ TravelAssistant(medium) ──→ ReviewerAgent → 通过 → 返回
                                                                   → 不通过 → 重做 → 定点复查 → 返回
           → complex ─→ PlannerAgent → TravelAssistant(complex) ─→ ReviewerAgent → 通过 → 返回
                                                                                  → 不通过 → 重做 → 定点复查 → 返回
```

Orchestration logic lives in the Command layer (deterministic if/else), not in an LLM.

## Agents

### RouterAgent

- **Role**: Classify user input complexity as `simple`, `medium`, or `complex`
- **Provider/Model**: groq / glm-5.1
- **Temperature**: 0 (classification must be stable)
- **Tools**: None
- **Instructions**:

```
你是一个任务复杂度分类器。根据用户的旅行问题，判断复杂度并只返回一个词：

- simple：单一信息查询（查天气、查门票、查单个景点）
- medium：需要综合多个信息源推荐（推荐景点、比较方案）
- complex：涉及多天/多城市/完整行程规划

只输出 simple、medium 或 complex，不要输出任何其他内容。
```

- **Expected mappings**:
  - "北京今天天气" → simple
  - "北京天气怎么样，推荐景点" → medium
  - "帮我规划3天北京深度游" → complex

### PlannerAgent

- **Role**: Decompose complex tasks into step-by-step plan (complex only)
- **Provider/Model**: groq / glm-5.1
- **Temperature**: 0.3
- **Tools**: None
- **Instructions**:

```
你是一个旅行规划专家。你的任务是把用户的复杂旅行需求拆解成分步计划。

规则：
- 每个步骤必须是一个独立的、可执行的子任务
- 按逻辑顺序排列
- 只输出计划，不要执行

输出格式（严格遵循）：
1. [具体子任务描述]
2. [具体子任务描述]
...
```

- **Output passed to TravelAssistant** as:

```php
$executionPrompt = "请按照以下计划执行：\n{$plan->text}\n\n用户原始需求：{$prompt}";
```

### TravelAssistant (modified)

- **Role**: Core executor with mode-dependent instructions
- **Provider/Model**: groq / glm-5.1 (unchanged)
- **Temperature**: 0.5 (unchanged)
- **Tools**: All 6 existing tools (unchanged)
- **Change**: Add `mode` constructor parameter, switch instructions by mode

```php
public function __construct(
    private int $userId = 0,
    private string $mode = 'simple',
) {}

public function instructions(): Stringable|string
{
    return match ($this->mode) {
        'simple' => $this->simpleInstructions(),
        'medium' => $this->reactInstructions(),
        'complex' => $this->planReactInstructions(),
    };
}
```

**simple instructions**:
```
你是一个智能旅行助手。直接使用工具回答用户问题，简洁明了。
```

**medium instructions (ReAct)**:
```
你是一个智能旅行助手。
每次调用工具之前，你必须先输出思考过程：你掌握了什么、还缺什么、为什么调这个工具。
每次只执行一个工具调用。
收集到足够信息后给出最终答案。
```

**complex instructions (Plan + ReAct)**:
```
你是一个智能旅行助手。你会收到一份分步计划。
请严格按照计划的步骤逐步执行，每步：
1. 说明当前执行计划的第几步
2. 说出思考过程
3. 调用工具
不要跳步，不要合并步骤。全部执行完后输出完整的最终答案。
```

### ReviewerAgent

- **Role**: Quality check results, verify hard facts only (medium + complex)
- **Provider/Model**: groq / glm-5.1
- **Temperature**: 0 (consistent evaluation)
- **Tools**: CheckTicketAvailability, GetWeather, GetPreferences (3 verification-only tools, no GetAttraction or SavePreference)
- **Instructions**:

```
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
```

## Reflection Flow

```
第一次执行结果
  ↓
ReviewerAgent 完整评审（3条全查）
  ├─ "无需改进" → ✅ 返回
  └─ 有问题 → TravelAssistant 带反馈重做
                ↓
              ReviewerAgent 定点复查（只查上次出问题的点）
                ├─ "无需改进" → ✅ 修正后验证通过
                └─ 仍有问题 → ⚠️ 返回结果 + 警告提示
```

- Maximum retry: 1 time
- Recheck is scoped: only verifies the specific issues found in first review
- Recheck prompt:

```php
$recheck = $reviewerAgent->prompt(
    "上一次检查发现以下问题：\n{$review->text}\n\n请只验证这些问题是否已修正：\n{$result->text}"
);
```

## Command Orchestration

All orchestration logic in `TravelAssistantCommand::singlePrompt()`:

```php
// 1. Route
$level = RouterAgent->prompt($input);

// 2. Plan (complex only)
if ($level === 'complex') {
    $plan = PlannerAgent->prompt($input);
    $executionPrompt = "请按照以下计划执行：\n{$plan}\n\n用户原始需求：{$input}";
}

// 3. Execute
$result = TravelAssistant(mode: $level)->prompt($executionPrompt);

// 4. Review (medium + complex)
if ($level !== 'simple') {
    $review = ReviewerAgent->prompt($result);
    if (需要改进) {
        $result = TravelAssistant->prompt(反馈 + 原始需求);
        $recheck = ReviewerAgent->prompt(定点复查);
        // 不管结果如何，返回
    }
}
```

## User-Facing Status

| State | Message |
|-------|---------|
| Review passed | `✅ 质量检查通过` |
| Fixed and recheck passed | `✅ 修正后验证通过` |
| Fixed but recheck failed | `⚠️ 部分问题仍未解决，建议自行确认` |
| Simple (no review) | No status message |

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Ai/Agents/RouterAgent.php` | Create | Complexity classifier |
| `app/Ai/Agents/PlannerAgent.php` | Create | Plan generator |
| `app/Ai/Agents/ReviewerAgent.php` | Create | Quality reviewer with tools |
| `app/Ai/Agents/TravelAssistant.php` | Modify | Add mode parameter, 3 instruction sets |
| `app/Console/Commands/TravelAssistantCommand.php` | Modify | Orchestration logic |
| `tests/Feature/TravelAssistantTest.php` | Modify | Add tests for new agents |

## Design Decisions

1. **Orchestration in Command, not Agent**: Flow control is deterministic (if/else), should not be LLM-driven.
2. **ReviewerAgent has tools**: Verification must be fact-based, not LLM judgment on text.
3. **Only 3 verification tools for reviewer**: Reviewer should not recommend attractions or save preferences.
4. **Max 1 retry**: More retries risk LLM making things worse or creating loops.
5. **Scoped recheck after retry**: Only verify the specific issues found, not a full re-review, to avoid introducing new false negatives.
6. **Simple tasks skip Reflection**: Cost exceeds benefit for single-info queries.
7. **Mode via constructor, not separate classes**: All three modes share the same tools, only instructions differ.
