<?php

namespace App\Providers;

use App\Services\AI\ClaudeClient;
use App\Services\AI\ConfidenceEvaluator;
use App\Services\AI\IntentDetector;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ReplyEngine;
use App\Services\AI\ToneAdapter;
use App\Services\AI\ToolRegistry;
use App\Services\Channels\ChannelManager;
use App\Services\Comments\CommentResponder;
use App\Services\Escalation\EscalationDispatcher;
use App\Services\Memory\CustomerMemory;
use App\Services\Products\ProductCatalog;
use App\Services\Products\RecommendationEngine;
use App\Services\Sales\CheckoutCollector;
use App\Services\Sales\PaymentLinkGenerator;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClaudeClient::class);
        $this->app->singleton(PromptBuilder::class);
        $this->app->singleton(IntentDetector::class);
        $this->app->singleton(ToneAdapter::class);
        $this->app->singleton(ConfidenceEvaluator::class);

        $this->app->singleton(ProductCatalog::class);
        $this->app->singleton(RecommendationEngine::class);

        $this->app->singleton(CheckoutCollector::class);
        $this->app->singleton(PaymentLinkGenerator::class);

        $this->app->singleton(EscalationDispatcher::class);

        $this->app->singleton(CustomerMemory::class);

        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(ReplyEngine::class);

        $this->app->singleton(CommentResponder::class);
    }
}
