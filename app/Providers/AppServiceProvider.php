<?php

namespace App\Providers;

use App\AI\Contracts\AIProvider;
use App\AI\DeepSeek\DeepSeekProvider;
use App\Models\AiUsageLog;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\PromptTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Policies\ContentRequestPolicy;
use App\Policies\ContentVariationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AIProvider::class, DeepSeekProvider::class);

        // The openai-php client default transport has no connect/request
        // timeout; without an explicit PSR-18 client a hung provider socket
        // would pin a queue worker far past Horizon's timeout. Guzzle is a
        // PSR-18 client as of 7.9, so it can be injected directly.
        $this->app->bind(\OpenAI\Client::class, function (): \OpenAI\Client {
            return \OpenAI::factory()
                ->withBaseUri((string) config('ai.base_url'))
                ->withApiKey((string) config('ai.api_key'))
                ->withHttpClient(new \GuzzleHttp\Client([
                    'timeout' => (int) config('ai.timeout'),
                ]))
                ->make();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ContentRequest::class, ContentRequestPolicy::class);
        Gate::policy(ContentVariation::class, ContentVariationPolicy::class);
        Gate::policy(PromptTemplate::class, AdminPolicy::class);
        Gate::policy(AiUsageLog::class, AdminPolicy::class);
        Gate::policy(Setting::class, AdminPolicy::class);
        Gate::policy(User::class, AdminPolicy::class);
    }
}
