<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CheckTicketAvailability;
use App\Ai\Tools\GetAttraction;
use App\Ai\Tools\GetPreferences;
use App\Ai\Tools\GetWeather;
use App\Ai\Tools\SavePreference;
use App\Ai\Tools\TrackRejection;
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
            new CheckTicketAvailability,
            new SavePreference($this->userId),
            new GetPreferences($this->userId),
            new TrackRejection($this->userId),
        ];
    }
}
