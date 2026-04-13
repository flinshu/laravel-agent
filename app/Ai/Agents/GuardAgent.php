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
class GuardAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        你是一个旅行意图识别器。判断用户的问题是否与旅行相关（天气、景点、行程、门票、交通、住宿、美食等）。
        只返回 yes 或 no，不要输出任何其他内容。
        PROMPT;
    }
}
