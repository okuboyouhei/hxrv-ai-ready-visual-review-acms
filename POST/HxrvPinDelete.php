<?php

namespace Acms\Plugins\HxrvAcms\POST;

use ACMS_POST;
use DB;
use SQL;

/**
 * POSTモジュール: HxrvPinDelete
 * フォームトリガー名: ACMS_POST_HxrvPinDelete
 */
class HxrvPinDelete extends ACMS_POST
{
    protected $isCSRF = true;

    public function post()
    {
        if (!defined('SUID') || !SUID) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $pinId = (int) $this->Post->get('pin_id');

        if (!$pinId) {
            $this->jsonResponse(['success' => false, 'error' => 'pin_id is required']);
        }

        // 存在確認 & ブログIDチェック
        $selSQL = SQL::newSelect('hxrv_pins');
        $selSQL->addSelect('pin_blog_id');
        $selSQL->addWhereOpr('pin_id', $pinId);
        $row = DB::query($selSQL->get(dsn()), 'row');

        if (!$row) {
            $this->jsonResponse(['success' => false, 'error' => 'Pin not found']);
        }

        if ((int) $row['pin_blog_id'] !== (int) BID) {
            $this->jsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $delSQL = SQL::newDelete('hxrv_pins');
        $delSQL->addWhereOpr('pin_id', $pinId);
        $res = DB::query($delSQL->get(dsn()), 'exec');

        $this->jsonResponse(['success' => (bool) $res]);

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
