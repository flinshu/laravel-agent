<?php

namespace App\Ai\Tools;

use App\Models\UserPreference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetPreferences implements Tool
{
    public function __construct(private int $userId) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return '获取用户已保存的旅行偏好。在推荐景点前调用此工具，了解用户的喜好以提供更个性化的建议。';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $preferences = UserPreference::where('user_id', $this->userId)->get();

        if ($preferences->isEmpty()) {
            return '该用户暂无已保存的偏好。';
        }

        return $preferences
            ->map(fn (UserPreference $pref) => "- {$pref->key}: {$pref->value}")
            ->implode("\n");
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
