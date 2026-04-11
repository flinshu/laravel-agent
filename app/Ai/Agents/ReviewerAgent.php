<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CheckTicketAvailability;
use App\Ai\Tools\GetPreferences;
use App\Ai\Tools\GetWeather;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('groq')]
#[Model('glm-5.1')]
#[MaxSteps(6)]
#[Temperature(0)]
class ReviewerAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(private int $userId = 0) {}

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
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
        PROMPT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new CheckTicketAvailability,
            new GetWeather,
            new GetPreferences($this->userId),
        ];
    }
}
