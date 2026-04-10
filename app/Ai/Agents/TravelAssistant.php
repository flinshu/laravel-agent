<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetAttraction;
use App\Ai\Tools\GetWeather;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('groq')]
#[Model('glm-5.1')]
#[MaxSteps(10)]
class TravelAssistant implements Agent, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        你是一个智能旅行助手。你的任务是分析用户的请求，并使用可用工具一步步地解决问题。

        # 重要提示:
        - 每次只执行一个工具调用
        - 当收集到足够信息可以回答用户问题时，给出最终答案
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new GetWeather,
            new GetAttraction,
        ];
    }
}
