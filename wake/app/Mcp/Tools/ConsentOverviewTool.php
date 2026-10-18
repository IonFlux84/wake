<?php

namespace Wake\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Consent\ConsentRecords;
use Wake\Consent\ConsentSettings;
use Wake\Content\Index;

#[Name('consent_overview')]
#[Description('How visitors decide in the consent banner: number of decisions, accept/decline/own choice, agreement per category, whether a banner is needed and which external services are embedded.')]
class ConsentOverviewTool extends WakeTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Number of days back from today, default 30.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $days = max(1, min(366, (int) $request->get('days', 30)));
        $summary = app(ConsentRecords::class)->summary(CarbonImmutable::now()->subDays($days - 1), CarbonImmutable::now(), 0);
        $settings = app(ConsentSettings::class);
        unset($summary['recent']);

        return Response::json([
            ...$summary,
            'banner_needed' => $settings->bannerNeeded(),
            'banner_version' => (int) $settings->get('version'),
            'categories_offered' => $settings->categories(),
            'embedded_services' => app(Index::class)->services(),
        ]);
    }
}
