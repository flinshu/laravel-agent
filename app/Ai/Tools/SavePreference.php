<?php

namespace App\Ai\Tools;

use App\Models\UserPreference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SavePreference implements Tool
{
    public function __construct(private int $userId) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return '保存用户的旅行偏好。当用户表达喜好时调用此工具，例如喜欢的景点类型、预算范围、出行方式等。';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        UserPreference::updateOrCreate(
            ['user_id' => $this->userId, 'key' => $request['key']],
            ['value' => $request['value']],
        );

        return "已保存用户偏好：{$request['key']} = {$request['value']}";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description('偏好类型，如: favorite_type, budget, transport, food_preference')
                ->required(),
            'value' => $schema->string()
                ->description('偏好内容，如: 历史文化, 500元以内, 地铁出行, 喜欢海鲜')
                ->required(),
        ];
    }
}
