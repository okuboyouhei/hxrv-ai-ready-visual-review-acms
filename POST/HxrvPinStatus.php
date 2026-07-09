<?php

namespace Acms\Plugins\HxrvAcms\POST;

use ACMS_POST;
use DB;
use SQL;

/**
 * POSTモジュール: HxrvPinStatus
 * フォームトリガー名: ACMS_POST_HxrvPinStatus
 * open ↔ resolved のトグル
 */
class HxrvPinStatus extends ACMS_POST
{
    protected $isCSRF = true;

    public function post()
    {
        if (!defined('SUID') || !SUID) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $pinId     = (int) $this->Post->get('pin_id');
        $newStatus = trim($this->Post->get('status'));

        if (!$pinId || !in_array($newStatus, ['open', 'resolved'], true)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid parameters']);
        }

        // ブログIDチェック
        $selSQL = SQL::newSelect('hxrv_pins');
        $selSQL->addSelect('pin_blog_id');
        $selSQL->addWhereOpr('pin_id', $pinId);
        $row = DB::query($selSQL->get(dsn()), 'row');

        if (!$row || (int) $row['pin_blog_id'] !== (int) BID) {
            $this->jsonResponse(['success' => false, 'error' => 'Not found or forbidden'], 403);
        }

        $updSQL = SQL::newUpdate('hxrv_pins');
        $updSQL->addUpdate('pin_status',     $newStatus);
        $updSQL->addUpdate('pin_updated_at', date('Y-m-d H:i:s'));
        $updSQL->addWhereOpr('pin_id', $pinId);
        $res = DB::query($updSQL->get(dsn()), 'exec');

        $this->jsonResponse(['success' => (bool) $res, 'status' => $newStatus]);

        return $this->Post;
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }
}
