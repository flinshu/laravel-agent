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
