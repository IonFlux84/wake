<?php

namespace Wake\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;

#[Name('weekly_review')]
#[Description('A short, friendly review of the last seven days: what was read, where readers came from, what stood out, and one suggestion.')]
class WeeklyReviewPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'TEXT'
        Write a short weekly review of this website for its editors.

        1. Call stats_overview with period "7d".
        2. For the two most read posts, call stats_post to see where readers came from and how far they read.
        3. Write at most eight sentences: how the week went compared with the week before, what was read, which
           source mattered most, one thing that stood out, and one concrete suggestion (for example which post
           deserves a follow-up). Use plain numbers, no tables. Mention that visitors are counted without cookies.
        TEXT);
    }
}
