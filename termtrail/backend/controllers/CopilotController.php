<?php
declare(strict_types=1);
namespace TermTrail\controllers;

use TermTrail\config\Database;
use TermTrail\helpers\{Audit, Response};
use TermTrail\middleware\Auth;
use TermTrail\models\ContractModel;
use TermTrail\services\CopilotService;

final class CopilotController
{
    private const QUESTIONS_PER_HOUR = 30;

    private static function contract(int $id, array $u): array
    {
        $c = $id > 0 ? ContractModel::find($id, $u) : null;
        if (!$c) { Response::error('Contract not found.', 404); }
        return $c;
    }

    public static function ask(array $in): never
    {
        $u = Auth::requireApi();
        $c = self::contract((int)($in['contract_id'] ?? 0), $u);
        $q = trim((string)($in['question'] ?? ''));
        if (mb_strlen($q) < 3 || mb_strlen($q) > 500) { Response::error('Ask a question between 3 and 500 characters.', 422, ['question' => 'Ask a question between 3 and 500 characters.']); }
        $pdo = Database::pdo();
        $chunks = $pdo->prepare('SELECT COUNT(*) FROM document_chunks WHERE contract_id=?');
        $chunks->execute([$c['id']]);
        if ((int)$chunks->fetchColumn() === 0) { Response::error($c['extraction_error'] ?: 'This contract has no extracted text to search.', 422); }
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id=? AND role='user' AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $cnt->execute([$u['id']]);
        if ((int)$cnt->fetchColumn() >= self::QUESTIONS_PER_HOUR) { Response::error('Question limit reached for this hour. Please try again later.', 429); }

        $res = CopilotService::ask((int)$c['id'], $q);
        $ins = $pdo->prepare('INSERT INTO chat_messages (contract_id,user_id,role,message,sources_json) VALUES (?,?,?,?,?)');
        $ins->execute([$c['id'], $u['id'], 'user', $q, null]);
        $ins->execute([$c['id'], $u['id'], 'assistant', $res['answer'], json_encode(['sources' => $res['sources'], 'mode' => $res['mode'], 'warning' => $res['warning']], JSON_UNESCAPED_UNICODE)]);
        Audit::log((int)$u['id'], 'copilot.ask', 'contract', (int)$c['id'], ['mode' => $res['mode'], 'method' => $res['method']]);
        Response::ok(['answer' => $res['answer'], 'sources' => $res['sources'], 'mode' => $res['mode'], 'warning' => $res['warning']]);
    }

    public static function history(): never
    {
        $u = Auth::requireApi();
        $c = self::contract((int)($_GET['contract_id'] ?? 0), $u);
        $st = Database::pdo()->prepare('SELECT id, role, message, sources_json, created_at FROM chat_messages WHERE contract_id=? AND user_id=? ORDER BY id DESC LIMIT 60');
        $st->execute([$c['id'], $u['id']]);
        $rows = array_reverse($st->fetchAll());
        foreach ($rows as &$r) { $r['meta'] = $r['sources_json'] ? json_decode($r['sources_json'], true) : null; unset($r['sources_json']); }
        unset($r);
        Response::ok(['items' => $rows]);
    }

    public static function clear(array $in): never
    {
        $u = Auth::requireApi();
        $c = self::contract((int)($in['contract_id'] ?? 0), $u);
        Database::pdo()->prepare('DELETE FROM chat_messages WHERE contract_id=? AND user_id=?')->execute([$c['id'], $u['id']]);
        Response::ok(['message' => 'Conversation cleared.']);
    }
}
