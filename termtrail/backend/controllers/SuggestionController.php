<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\helpers\Response;
use TermTrail\middleware\Auth;
use TermTrail\services\{SuggestionException, SuggestionService};

/** Thin HTTP wrapper; the rules live in SuggestionService (unit-tested). */
final class SuggestionController
{
    public static function approve(array $in): never
    {
        $u = Auth::requireApi('manager');
        $ov = is_array($in['overrides'] ?? null) ? $in['overrides'] : [];
        try { $r = SuggestionService::approve((int)($in['id'] ?? 0), $u, $ov); }
        catch (SuggestionException $e) { Response::error($e->getMessage(), $e->status, $e->errors); }
        Response::ok(['message' => $r['kind'] === 'obligation' ? 'Approved. The obligation was created.' : 'Approved. The payment was created.', 'created_id' => $r['created_id'], 'kind' => $r['kind']]);
    }

    public static function reject(array $in): never
    {
        $u = Auth::requireApi('manager');
        try { SuggestionService::reject((int)($in['id'] ?? 0), $u, isset($in['reason']) ? (string)$in['reason'] : null); }
        catch (SuggestionException $e) { Response::error($e->getMessage(), $e->status, $e->errors); }
        Response::ok(['message' => 'Suggestion rejected.']);
    }
}
