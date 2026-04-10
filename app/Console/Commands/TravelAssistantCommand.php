<?php

namespace App\Console\Commands;

use App\Ai\Agents\TravelAssistant;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('travel:ask {prompt? : The travel question to ask} {--chat : Enter interactive multi-turn conversation mode}')]
#[Description('Ask the travel assistant agent for weather-based attraction recommendations')]
class TravelAssistantCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::firstOrCreate(
            ['email' => 'traveler@example.com'],
            ['name' => 'Traveler', 'password' => bcrypt('password')],
        );

        if ($this->option('chat')) {
            return $this->chat($user);
        }

        return $this->singlePrompt($user);
    }

    /**
     * Handle a single prompt.
     */
    private function singlePrompt(User $user): int
    {
        $prompt = $this->argument('prompt')
            ?? $this->ask('What would you like to know?', '请查询北京天气并推荐合适的旅游景点。');

        $agent = new TravelAssistant($user->id);

        $this->info('Thinking...');

        $response = $agent->forUser($user)->prompt($prompt);

        $this->printResponse($response);

        return self::SUCCESS;
    }

    /**
     * Handle interactive multi-turn conversation.
     */
    private function chat(User $user): int
    {
        $this->info('智能旅行助手 - 交互模式');
        $this->info('输入你的问题，输入 "quit" 退出');
        $this->newLine();

        $agent = new TravelAssistant($user->id);
        $conversationId = null;

        while (true) {
            $prompt = $this->ask('你');

            if (in_array(strtolower((string) $prompt), ['quit', '退出', 'exit', ''])) {
                $this->info('再见！');

                break;
            }

            $this->info('Thinking...');

            if ($conversationId) {
                $response = $agent->continue($conversationId, as: $user)->prompt($prompt);
            } else {
                $response = $agent->forUser($user)->prompt($prompt);
            }

            $conversationId = $response->conversationId;

            $this->printResponse($response);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * Print the agent response.
     */
    private function printResponse(mixed $response): void
    {
        $this->newLine();

        foreach ($response->steps as $index => $step) {
            $toolNames = collect($step->toolCalls)->pluck('name')->implode(', ');

            if ($toolNames) {
                $this->components->twoColumnDetail('Step '.($index + 1), $toolNames);
            }
        }

        $this->newLine();
        $this->line($response->text);
    }
}
