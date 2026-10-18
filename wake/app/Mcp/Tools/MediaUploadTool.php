<?php

namespace Wake\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Wake\Accounts\Permissions;
use Wake\Content\Query;
use Wake\Content\Writer;
use Wake\Media\Images;

#[Name('media_upload')]
#[Description('Add an image to an entry, from base64 data. Returns the file name to use in Markdown, e.g. ![Alt text](name.jpg), or as "cover".')]
class MediaUploadTool extends WakeTool
{
    protected string $scope = 'content:write';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Entry id.')->required(),
            'name' => $schema->string()->description('File name with extension, e.g. "harbour.jpg".')->required(),
            'data' => $schema->string()->description('The file as base64. At most 10 MB.')->required(),
        ];
    }

    protected function run(Request $request): Response
    {
        $entry = (new Query)->find((string) $request->get('id'));

        if (! $entry || ! Permissions::mayEdit(auth()->user(), $entry)) {
            return Response::error('There is no such entry, or your role may not change it.');
        }

        $extension = strtolower(pathinfo((string) $request->get('name'), PATHINFO_EXTENSION));

        if (! in_array($extension, Images::FORMATS, true)) {
            return Response::error('Only images can be uploaded: '.implode(', ', Images::FORMATS).'.');
        }

        $data = base64_decode((string) $request->get('data'), true);

        if ($data === false || strlen($data) > 10 * 1024 * 1024 || @getimagesizefromstring($data) === false) {
            return Response::error('The data is not a valid image of at most 10 MB.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'wake');
        file_put_contents($temp, $data);

        try {
            $name = app(Writer::class)->addMedia($entry->id(), (string) $request->get('name'), $temp);
        } finally {
            @unlink($temp);
        }

        return Response::json(['id' => $entry->id(), 'name' => $name, 'markdown' => "![]({$name})"]);
    }
}
