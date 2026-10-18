<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Localization\Languages;
use Wake\Markdown\Cards\CardTypes;

#[Name('content_cards')]
#[Description('The cards this website offers for the Markdown body (::: name … :::): built into Wake, from plugins and from the theme. Each comes with its title, what it is for, its settings (value after the name, key="value" fields, whether it holds text) and a Markdown example. Use only these cards and fill required settings.')]
class ContentCardsTool extends WakeTool
{
    protected string $scope = 'content:read';

    public function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->string()->description('Language of titles and labels, default the site\'s default language.'),
        ];
    }

    protected function run(Request $request): Response
    {
        $language = $request->get('language') ?: app(Languages::class)->default();

        return Response::json([
            'cards' => app(CardTypes::class)->toArray($language),
            'syntax' => '::: name value key="value"'."\n".'Markdown inside the card'."\n".':::',
            'note' => 'Image fields take a file of the media library ("media/…") or a file name in the entry\'s own media folder.',
        ]);
    }
}
