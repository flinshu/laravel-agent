<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetAttraction;
use App\Ai\Tools\GetPreferences;
use App\Ai\Tools\GetWeather;
use App\Ai\Tools\SavePreference;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('groq')]
#[Model('glm-5.1')]
#[MaxSteps(10)]
class TravelAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(private int $userId = 0) {}

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
        - 当用户表达旅行偏好时（如喜欢历史景点、预算范围、出行方式），使用 SavePreference 工具保存
        - 在推荐景点前，先使用 GetPreferences 工具查看用户是否有已保存的偏好，据此提供个性化建议
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
            new SavePreference($this->userId),
            new GetPreferences($this->userId),
        ];
    }
}
