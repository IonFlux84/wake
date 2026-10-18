<?php

namespace Wake\Http\Controllers\Panel;

use Illuminate\View\View;
use Wake\Content\ContentTypes;

class ContentController
{
    public function index(ContentTypes $types, string $type = 'post'): View
    {
        $contentType = $types->get($type) ?? abort(404);

        return view('wake::panel.entries', [
            'type' => $type,
            'title' => ['post' => __('Posts'), 'page' => __('Pages'), 'tag' => __('Tags')][$type] ?? $contentType->title,
            'active' => $type === 'page' ? 'pages' : 'posts',
        ]);
    }

    public function edit(ContentTypes $types, string $type): View
    {
        abort_unless($types->get($type), 404);

        return view('wake::panel.editor', [
            'type' => $type,
            'active' => $type === 'page' ? 'pages' : 'posts',
        ]);
    }
}
