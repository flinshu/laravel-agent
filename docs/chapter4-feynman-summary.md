# 第四章：智能体经典范式构建 — 费曼学习法总结

> 核心问题：我们知道 LLM 很聪明，但怎么让它**有条理地**完成复杂任务？有哪些经过验证的"工作方式"？

---

## 用一句话概括这章

> 这章教你三种让 AI Agent 完成复杂任务的经典套路：**边想边做**（ReAct）、**先计划再执行**（Plan-and-Solve）、**做完再反思改进**（Reflection）——三种方法各有适用场景，搭配使用威力倍增。

---

## 一、ReAct：边想边做的侦探

### 核心思想：思考和行动交替进行

**ReAct = Reasoning + Acting**，由 Shunyu Yao 于 2022 年提出。

**比喻**：就像一个侦探破案——
- 不是坐在椅子上想清楚所有事再动手
- 而是去现场看一眼，得到线索，再推理，再行动，再观察

**运作流程**：

```
Thought（思考）→ Action（行动）→ Observation（观察）→ Thought（再思考）→ ...
```

**每一轮循环**：
1. **Thought**：模型的"内心独白"——分析情况、制定下一步
2. **Action**：调用工具（搜索、计算等），或者 `Finish[最终答案]`
3. **Observation**：工具返回的结果，追加进历史记录

### 关键机制：提示词约束输出格式

ReAct 最聪明的地方不是什么高深算法，而是**用提示词强制模型按固定格式输出**：

```
Thought: 我需要搜索华为最新手机...
Action: Search[华为最新手机型号]
```

代码再用正则表达式解析出 `Thought` 和 `Action`，执行工具，把结果作为 `Observation` 追加回去，进入下一轮。

**历史记录不断增长**，模型每次都能"看见"所有历史，做出更好决策：

```python
# ReAct 核心逻辑
while current_step < max_steps:
    prompt = 格式化(工具列表 + 用户问题 + 历史记录)
    response = llm.think(prompt)
    thought, action = 解析(response)
    
    if action == "Finish":
        return 最终答案
    
    observation = 执行工具(action)
    history.append(action + observation)  # ← 历史不断累积
```

### ReAct 的优缺点

| 优点 | 缺点 |
|------|------|
| 可解释性强（能看到每步思考） | 严重依赖 LLM 遵循格式的能力 |
| 动态纠错（根据观察调整策略） | 串行调用，步骤多时很慢 |
| 天然工具协同 | 提示词脆弱，模板微调就可能出错 |
| 适合实时信息查询 | 可能陷入局部最优，缺乏全局视野 |

---

## 二、Plan-and-Solve：先画蓝图再施工的建筑师

### 核心思想：先想清楚，再动手

**比喻**：ReAct 是侦探（边查边推理），Plan-and-Solve 是建筑师——
- 开工前必须先画完整的施工图
- 然后严格按图纸一步步建造

**两个阶段**：

```
阶段1：规划（Planner）→ 生成结构化计划
阶段2：执行（Executor）→ 按计划逐步完成
```

### 规划阶段：让模型输出 Python 列表

提示词设计的关键：**强制结构化输出**，不要自然语言，要能被代码直接解析的格式：

```python
# 提示词要求模型输出 Python 列表
问题: 一个水果店周一卖出15个苹果...

请严格按照以下格式输出：
```python
["步骤1", "步骤2", "步骤3", ...]
```
```

模型输出后，用 `ast.literal_eval()` 直接解析成 Python 列表——比解析自然语言稳定得多。

### 执行阶段：带状态的串行执行

每一步执行时，都把之前所有步骤的结果作为上下文传进去：

```python
for i, step in enumerate(plan):
    prompt = 格式化(原始问题 + 完整计划 + 历史步骤结果 + 当前步骤)
    result = llm.think(prompt)
    history += f"步骤{i+1}: {step}\n结果: {result}\n"
```

**状态管理是关键**：每步的结果通过 `history` 字符串传递给下一步，形成信息流。

### 实际运行效果

水果店数学题：
```
规划阶段：["计算周一销量: 15", "计算周二: 15×2=30", "计算周三: 30-5=25", "求总和: 15+30+25=70"]
执行阶段：步骤1→15，步骤2→30，步骤3→25，步骤4→70
最终答案：70 ✅
```

### Plan-and-Solve 适用场景

- 多步数学题（需要先列计算步骤）
- 报告撰写（先规划结构，再逐段填充）
- 代码生成（先构思架构，再逐模块实现）
- **任何"需要全局规划"的结构性任务**

---

## 三、Reflection：写完初稿再找人评审的迭代改进

### 核心思想：做完不算完，反思才能进步

**比喻**：就像写作——
1. 先写初稿
2. 请评审员（另一个 LLM 角色）挑毛病
3. 根据反馈改进
4. 再评审，再改进……直到"无需改进"

**三步循环**：

```
执行（生成初稿）→ 反思（评审找问题）→ 优化（根据反馈改进）→ 循环
```

### 三个关键提示词

Reflection 机制需要**三种不同角色**的提示词：

**1. 初始执行提示词**（程序员角色）：
```
你是资深 Python 程序员，请编写一个找素数的函数，要求包含函数签名、文档字符串，遵循 PEP 8。
```

**2. 反思提示词**（评审员角色）：
```
你是极其严格的代码评审专家，专注于算法效率。
请分析这段代码的时间复杂度，是否存在更优的算法？
如果代码已达最优，才能回答"无需改进"。
```

**3. 优化提示词**（改进者角色）：
```
你是资深程序员，根据评审员反馈优化代码。
上一轮代码：{last_code}
评审员反馈：{feedback}
请输出优化后的版本。
```

### Memory 模块：短期记忆存储轨迹

Reflection 需要记住所有历史迭代，因此引入了一个简单的**记忆模块**：

```python
class Memory:
    def __init__(self):
        self.records = []  # 存储所有 execution 和 reflection 记录
    
    def add_record(self, record_type, content):
        # record_type: 'execution' 或 'reflection'
        self.records.append({"type": record_type, "content": content})
    
    def get_trajectory(self):
        # 把所有历史格式化成字符串，插入下一轮提示词
        ...
    
    def get_last_execution(self):
        # 获取最近一次生成的代码（供评审员审查）
        ...
```

**为什么需要记忆模块？**
- 上下文可能很长，直接传全部历史会引入大量冗余
- Memory 提供结构化的提取接口，按需获取
- 比喻：不是把所有草稿都堆给评审员，而是只给他看最新一版

### 实际迭代效果

素数函数任务：
```
第1轮：生成试除法（O(n√n)）
评审：时间复杂度高，建议用埃拉托斯特尼筛法

第2轮：生成筛法（O(n log log n)）
评审：无需改进 ✅

最终：返回高效的筛法实现
```

### 停止条件

```python
if "无需改进" in feedback:
    break  # 停止迭代
```

加上 `max_iterations` 作为安全阀，防止无限循环。

---

## 四、三种范式的对比

| 维度 | ReAct | Plan-and-Solve | Reflection |
|------|-------|---------------|------------|
| 核心思路 | 边想边做 | 先计划再执行 | 执行后反思迭代 |
| 适合场景 | 实时信息查询、工具调用 | 结构化推理、多步骤任务 | 质量要求高的生成任务 |
| LLM 调用次数 | 动态（每步1次） | 固定（1次规划+n次执行） | 固定（1次初稿+迭代×2） |
| 关键依赖 | 工具 + 格式化输出能力 | 结构化计划生成 | 反思评审质量 |
| 可解释性 | 极高（Thought可见） | 高（计划可见） | 中（迭代历史可见） |
| 自我纠错 | 有（基于Observation） | 无（严格按计划） | 强（专门设计纠错） |

---

## 五、比喻串联全章

想象你是一个厨师，要做一桌复杂的宴席：

- **ReAct**：边做边尝味道——炒一道菜，尝一下，咸了加水，淡了加盐，动态调整
- **Plan-and-Solve**：先写菜谱——把每道菜的步骤全部写清楚，然后照菜谱严格执行
- **Reflection**：做完让食客品评——摆出来请人试吃，听反馈，改进配方，再做一遍

**三者结合**：先写菜谱（Plan），边做边调整（ReAct），做完让人试吃改进（Reflection）——这就是现代 AI Agent 框架的核心设计思路。

---

## 六、为什么不直接用 LangChain/LlamaIndex

书中给出了三个理由，值得记住：

1. **理解设计机制**：框架帮你处理了格式解析、工具调用失败重试、防死循环等问题。不亲手做，不知道这些坑的存在。

2. **暴露工程挑战**：LLM 不一定每次都按格式输出；工具可能返回错误；循环可能无法终止——这些都是真实工程问题。

3. **成为创造者而非使用者**：掌握原理后，当框架无法满足需求时，你才有能力深度定制甚至从零构建。

---

## 七、这章对我们写 Laravel Agent 有什么启发

### 我们的 TravelAssistant 用的是哪种范式？

```php
// TravelAssistant.php
#[MaxSteps(10)]  ← 安全阀，防止无限循环（来自 ReAct 的思路）
class TravelAssistant implements Agent, Conversational, HasTools
```

Laravel AI 包帮我们实现了 **ReAct 范式的核心循环**：
- 模型思考 → 调用工具 → 得到 Observation → 再思考 → 直到 `Finish`
- `#[MaxSteps(10)]` 就是 ReAct 里的 `max_steps` 安全阀

### 如果要做 Plan-and-Solve 风格的 Agent

可以把规划和执行分成两个 Agent：

```php
// 第一步：规划 Agent
$plan = (new PlannerAgent)->prompt("请把这个任务拆解成步骤：{$task}");

// 第二步：执行 Agent，按步骤执行
foreach ($plan->steps as $step) {
    $result = (new ExecutorAgent)->prompt($step);
}
```

或者在 `instructions()` 里明确要求模型先规划再执行：

```php
public function instructions(): string
{
    return '请先列出解决问题的步骤计划，然后逐步执行每个步骤。';
}
```

### 如果要做 Reflection 风格的 Agent

可以在 Agent 外层加一个反思循环：

```php
$response = $agent->prompt($task);

// 让另一个 Agent 评审结果
$feedback = (new ReviewerAgent)->prompt("评审这个结果：{$response->text}");

if (!str_contains($feedback->text, '无需改进')) {
    // 带着反馈重新执行
    $response = $agent->prompt("请根据以下反馈改进：{$feedback->text}");
}
```

### 工具描述 = ReAct 的 Action 说明书

ReAct 里最关键的是**工具描述**——模型靠描述决定用哪个工具。
我们 Laravel Agent 里的 `description()` 方法就是这个：

```php
// GetWeather.php
public function description(): string
{
    return 'Get weather information for a city.';  // ← 模型靠这个决定何时调用
}
```

描述写得越清晰，模型做决策越准确——这是 ReAct 调试技巧里最重要的一条。

### 格式解析 = Laravel AI 帮你做的事

ReAct 里最麻烦的是**解析 LLM 输出格式**（正则匹配 Thought/Action）。
Laravel AI 框架的 tool_use 模式帮我们彻底省掉了这一步——模型直接输出结构化的函数调用，不需要手写解析逻辑。

这正是"用框架 vs 从零实现"最核心的差异。
