<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use Illuminate\Support\Carbon;

class UsageService
{
    public function cost(int $inputTokens, int $outputTokens): float
    {
        $inCost = $inputTokens / 1_000_000 * config('ai.pricing.input_per_million');
        $outCost = $outputTokens / 1_000_000 * config('ai.pricing.output_per_million');

        return round($inCost + $outCost, 6);
    }

    public function record(
        User $user,
        ContentRequest $request,
        ?ContentVariation $variation,
        string $model,
        string $operation,
        int $inputTokens,
        int $outputTokens,
        int $durationMs,
        string $status = 'success',
    ): AiUsageLog {
        return AiUsageLog::create([
            'user_id' => $user->id,
            'content_request_id' => $request->id,
            'content_variation_id' => $variation?->id,
            'provider' => config('ai.provider'),
            'model' => $model,
            'operation' => $operation,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'estimated_cost' => $this->cost($inputTokens, $outputTokens),
            'duration_ms' => $durationMs,
            'status' => $status,
        ]);
    }

    public function monthlyStats(Carbon $start, Carbon $end): array
    {
        $logs = AiUsageLog::whereBetween('created_at', [$start, $end]);

        $topUsers = AiUsageLog::whereBetween('ai_usage_logs.created_at', [$start, $end])
            ->join('users', 'users.id', '=', 'ai_usage_logs.user_id')
            ->selectRaw('users.name, sum(ai_usage_logs.total_tokens) as tokens')
            ->groupBy('users.name')
            ->orderByDesc('tokens')
            ->limit(5)
            ->get();

        return [
            'generations' => (clone $logs)->where('operation', 'generation')->count(),
            'variations' => (clone $logs)->distinct('content_variation_id')->count('content_variation_id'),
            'input_tokens' => (int) (clone $logs)->sum('input_tokens'),
            'output_tokens' => (int) (clone $logs)->sum('output_tokens'),
            'cost' => round((float) (clone $logs)->sum('estimated_cost'), 6),
            'top_users' => $topUsers,
        ];
    }
}
