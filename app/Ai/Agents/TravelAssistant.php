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
        You are an intelligent travel assistant. Your task is to analyze user requests and use available tools step by step to solve problems.

        ## Workflow
        1. When asked about travel to a city, first query the weather using the GetWeather tool.
        2. Based on the weather result, use the GetAttraction tool to find suitable attractions.
        3. Synthesize all information into a helpful, friendly travel recommendation.

        ## Guidelines
        - Always check the weather before recommending attractions.
        - Tailor attraction suggestions to the current weather conditions.
        - Provide practical tips (clothing, transportation, timing).
        - Reply in the same language the user uses.
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
