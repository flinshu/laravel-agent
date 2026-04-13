<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CheckTicketAvailability implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return '查询景点门票是否有余票。推荐景点后应调用此工具确认门票可用性，如果售罄则需要推荐备选景点。';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $attraction = $request['attraction'];
        $date = $request['date'] ?? now()->toDateString();

        $apiKey = config('services.tavily.key');

        if (empty($apiKey)) {
            return "{$attraction} 门票状态未知（未配置搜索API），建议直接前往或提前电话确认。";
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withToken($apiKey, 'Bearer')
                ->post('https://api.tavily.com/search', [
                    'query' => "{$attraction} {$date} 门票 余票 是否售罄",
                    'search_depth' => 'basic',
                    'include_answer' => true,
                ]);
        } catch (ConnectionException) {
            return "{$attraction} 门票状态查询超时，建议提前通过官方渠道确认。";
        }

        if ($response->failed()) {
            return "{$attraction} 门票状态查询失败，建议提前通过官方渠道确认。";
        }

        $answer = data_get($response->json(), 'answer');

        if ($answer) {
            return mb_convert_encoding($answer, 'UTF-8', 'UTF-8');
        }

        return "{$attraction} 门票信息暂未查到明确结果，建议通过官方渠道确认余票情况。";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'attraction' => $schema->string()
                ->description('景点名称，如 故宫、颐和园、长城')
                ->required(),
            'date' => $schema->string()
                ->description('查询日期，格式 YYYY-MM-DD，默认今天'),
        ];
    }
}
