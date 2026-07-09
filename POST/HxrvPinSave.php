<?php

namespace Acms\Plugins\HxrvAcms\POST;

use ACMS_POST;
use ACMS_RAM;
use DB;
use SQL;

/**
 * POSTモジュール: HxrvPinSave
 * フォームトリガー名: ACMS_POST_HxrvPinSave
 *
 * fetch での呼び出し:
 *   const fd = new FormData();
 *   fd.append('ACMS_POST_HxrvPinSave', 'submit');
 *   fd.append('formToken', document.querySelector('meta[name="csrf-token"]').content);
 *   ...
 *   fetch(apiBase, { method:'POST', body:fd })
 *
 * 確認済み:
 *   SUID = ログイン中ユーザーID
 *   $this->Post->get('field') = POSTデータ取得
 *   DB::query(['sql'=>..., 'params'=>[]], 'seq') = INSERT後にLAST_INSERT_IDを返す
 */
class HxrvPinSave extends ACMS_POST
{
    // a-blog cms の CSRF チェックを有効にする（デフォルト true）
    // JSから formToken を送ること（hxrv-overlay.htmlのfetch参照）
    protected $isCSRF = true;

    public function post()
    {
        // 認証チェック
        if (!defined('SUID') || !SUID) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        // 入力値取得・サニタイズ
        $pageUrl     = $this->sanitizeText($this->Post->get('page_url'),      2048);
        $selector    = $this->sanitizeText($this->Post->get('selector'),       1024);
        $offsetX     = $this->sanitizeFloat($this->Post->get('offset_x'));
        $offsetY     = $this->sanitizeFloat($this->Post->get('offset_y'));
        $comment     = $this->sanitizeText($this->Post->get('comment'),        5000);
        $textExcerpt = $this->sanitizeText($this->Post->get('text_excerpt'),   500);

        if (empty($pageUrl) || empty($selector)) {
            $this->jsonResponse(['success' => false, 'error' => 'page_url and selector are required']);
        }

        $author = ACMS_RAM::userName(SUID) ?: '匿名';
        $now    = date('Y-m-d H:i:s');

        $SQL = SQL::newInsert('hxrv_pins');
        $SQL->addInsert('pin_page_url',     $pageUrl);
        $SQL->addInsert('pin_selector',     $selector);
        $SQL->addInsert('pin_offset_x',     $offsetX);
        $SQL->addInsert('pin_offset_y',     $offsetY);
        $SQL->addInsert('pin_comment',      $comment);
        $SQL->addInsert('pin_text_excerpt', $textExcerpt);
        $SQL->addInsert('pin_author',       $author);
        $SQL->addInsert('pin_status',       'open');
        $SQL->addInsert('pin_blog_id',      BID);
        $SQL->addInsert('pin_created_at',   $now);
        $SQL->addInsert('pin_updated_at',   $now);

        // 'seq' モード = INSERT後に LAST_INSERT_ID を返す
        $newId = DB::query($SQL->get(dsn()), 'seq');

        if (!$newId) {
            $this->jsonResponse(['success' => false, 'error' => 'DB insert failed']);
        }

        $this->jsonResponse([
            'success' => true,
            'pin_id'  => (int) $newId,
        ]);

        return $this->Post;
    }

    // -------------------------------------------------------------------------

    private function sanitizeText(string $val, int $maxLen): string
    {
        return mb_substr(trim(strip_tags($val)), 0, $maxLen, 'UTF-8');
    }

    private function sanitizeFloat(string $val): float
    {
        return max(0.0, min(100.0, (float) $val));
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }
}
