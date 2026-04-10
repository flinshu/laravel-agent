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

        ## 工作流程
        1. 当用户询问某个城市的旅行建议时，首先使用 GetWeather 工具查询该城市的实时天气。
        2. 根据天气查询结果，使用 GetAttraction 工具搜索适合当前天气的旅游景点。
        3. 综合所有信息，为用户提供友好、实用的旅行推荐。

        ## 注意事项
        - 推荐景点前必须先查询天气。
        - 根据天气状况调整景点推荐（晴天推荐户外景点，雨天推荐室内场所）。
        - 提供实用的出行建议（穿衣、交通、时间安排）。
        - 使用用户所用的语言进行回复。
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
