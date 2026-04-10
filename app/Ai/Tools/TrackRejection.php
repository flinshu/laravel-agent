<?php

namespace App\Ai\Tools;

use App\Models\RejectionLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TrackRejection implements Tool
{
    public function __construct(private int $userId) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return '当用户拒绝或不满意某个景点推荐时，记录被拒绝的景点和原因。如果连续拒绝达到3次，返回分析结果供调整推荐策略。';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        RejectionLog::create([
            'user_id' => $this->userId,
            'attraction' => $request['attraction'],
            'reason' => $request['reason'] ?? null,
        ]);

        $recentRejections = RejectionLog::where('user_id', $this->userId)
            ->latest()
            ->limit(10)
            ->get();

        $totalCount = $recentRejections->count();

        if ($totalCount < 3) {
            return "已记录：用户不喜欢「{$request['attraction']}」。当前拒绝次数：{$totalCount}。";
        }

        $rejectedNames = $recentRejections->pluck('attraction')->implode('、');
        $reasons = $recentRejections
            ->filter(fn (RejectionLog $log) => filled($log->reason))
            ->map(fn (RejectionLog $log) => "- {$log->attraction}：{$log->reason}")
            ->implode("\n");

        $analysis = "⚠️ 用户已连续拒绝 {$totalCount} 个推荐，需要调整策略！\n\n";
        $analysis .= "被拒绝的景点：{$rejectedNames}\n\n";

        if (filled($reasons)) {
            $analysis .= "拒绝原因：\n{$reasons}\n\n";
        }

        $analysis .= '请根据以上信息反思推荐策略，避免推荐同类型景点，尝试完全不同风格的推荐。';

        return $analysis;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'attraction' => $schema->string()
                ->description('被用户拒绝的景点名称')
                ->required(),
            'reason' => $schema->string()
                ->description('用户拒绝的原因，如：太远、太贵、人太多、去过了、不感兴趣'),
        ];
    }
}
