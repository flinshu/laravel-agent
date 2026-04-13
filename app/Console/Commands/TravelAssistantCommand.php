<?php

namespace App\Console\Commands;

use App\Ai\Agents\GuardAgent;
use App\Ai\Agents\PlannerAgent;
use App\Ai\Agents\ReviewerAgent;
use App\Ai\Agents\RouterAgent;
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

        // Step 0: Guard — check if travel-related
        $isTravelRelated = trim(strtolower((new GuardAgent)->prompt($prompt)->text));

        if ($isTravelRelated !== 'yes') {
            $this->warn('抱歉，我是旅行助手，只能回答旅行相关的问题。');

            return self::SUCCESS;
        }

        // Step 1: Route — classify complexity
        $this->info('正在分析问题复杂度...');
        $level = trim(strtolower((new RouterAgent)->prompt($prompt)->text));

        if (! in_array($level, ['simple', 'medium', 'complex'])) {
            $level = 'medium';
        }

        $this->components->twoColumnDetail('复杂度', $level);

        // Step 2: Plan — complex only
        $executionPrompt = $prompt;

        if ($level === 'complex') {
            $this->info('正在生成计划...');
            $plan = (new PlannerAgent)->prompt($prompt);
            $this->line($plan->text);
            $this->newLine();
            $executionPrompt = "请按照以下计划执行：\n{$plan->text}\n\n用户原始需求：{$prompt}";
        }

        // Step 3: Execute
        $this->info('正在执行...');
        $agent = new TravelAssistant($user->id, mode: $level);
        $response = $agent->forUser($user)->prompt($executionPrompt);
        $this->printResponse($response);

        // Step 4: Review — medium and complex only
        if ($level !== 'simple') {
            $response = $this->review($user, $agent, $response, $prompt);
        }

        return self::SUCCESS;
    }

    /**
     * Review the agent response and retry if issues are found.
     */
    private function review(User $user, TravelAssistant $agent, mixed $response, string $originalPrompt): mixed
    {
        $this->info('正在质量检查...');
        $reviewer = new ReviewerAgent($user->id);
        $review = $reviewer->prompt(
            "请检查以下旅行推荐结果：\n{$response->text}\n\n用户原始需求：{$originalPrompt}"
        );

        if (str_contains($review->text, '无需改进')) {
            $this->info('✅ 质量检查通过');

            return $response;
        }

        // Retry once with feedback
        $this->warn('发现问题，正在修正...');
        $this->line($review->text);
        $this->newLine();

        $response = $agent->forUser($user)->prompt(
            "你之前的推荐存在以下问题：\n{$review->text}\n\n请修正后重新推荐。用户原始需求：{$originalPrompt}"
        );
        $this->printResponse($response);

        // Scoped recheck
        $this->info('正在复查...');
        $recheck = $reviewer->prompt(
            "上一次检查发现以下问题：\n{$review->text}\n\n请只验证这些问题是否已修正：\n{$response->text}"
        );

        if (str_contains($recheck->text, '无需改进')) {
            $this->info('✅ 修正后验证通过');
        } else {
            $this->warn('⚠️ 部分问题仍未解决，建议自行确认门票等信息。');
        }

        return $response;
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
            $stepLabel = 'Step '.($index + 1);

            if ($toolNames) {
                $this->components->twoColumnDetail($stepLabel, $toolNames);
            }

            if (filled($step->text) && $toolNames) {
                $this->line("  <fg=gray>[思考]: {$step->text}</>");
            }
        }

        $this->newLine();
        $this->line($response->text);
    }
}
