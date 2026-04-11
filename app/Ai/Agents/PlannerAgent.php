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
