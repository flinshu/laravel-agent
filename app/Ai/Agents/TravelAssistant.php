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
use Laravel\Ai\Attributes\Temperature;
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
#[Temperature(0.5)]
class TravelAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        private int $userId = 0,
        private string $mode = 'simple',
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
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
