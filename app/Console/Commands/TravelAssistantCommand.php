<?php

namespace App\Console\Commands;

use App\Ai\Agents\TravelAssistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('travel:ask {prompt? : The travel question to ask}')]
#[Description('Ask the travel assistant agent for weather-based attraction recommendations')]
class TravelAssistantCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $prompt = $this->argument('prompt')
            ?? $this->ask('What would you like to know?', 'Please check the weather in Beijing and recommend suitable tourist attractions.');

        $this->info("Asking travel assistant: {$prompt}");
        $this->newLine();

        $agent = new TravelAssistant;

        $this->info('Thinking...');

        $response = $agent->prompt($prompt);

        $this->newLine();
        $this->components->twoColumnDetail('Tool Calls', (string) $response->toolCalls->count());
        $this->components->twoColumnDetail('Steps', (string) $response->steps->count());
        $this->newLine();

        foreach ($response->steps as $index => $step) {
            $toolNames = collect($step->toolCalls)->pluck('name')->implode(', ');

            $this->components->twoColumnDetail(
                'Step '.($index + 1),
                $toolNames ?: 'Final response'
            );
        }

        $this->newLine();
        $this->line('--- Answer ---');
        $this->newLine();
        $this->line($response->text);

        return self::SUCCESS;
    }
}
