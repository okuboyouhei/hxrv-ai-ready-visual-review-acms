<?php

namespace Acms\Plugins\HxrvAcms\GET;

use ACMS_GET;
use DB;
use SQL;

/**
 * GETモジュール: HxrvPinApi
 *
 * テンプレート: <!-- BEGIN_MODULE HxrvPinApi --><!-- END_MODULE HxrvPinApi -->
 * 設置: themes/system/admin/app/hxrv/api.html
 *
 * GET ?action=list&page_url=...   → JSON ピン一覧
 * GET ?action=export&page_url=... → JSON Markdownエクスポート
 *
 * 確認済み:
 *   SUID = ログイン中ユーザーID
 *   DB::query(['sql'=>..., 'params'=>[]], 'mode')
 *   SQL::newSelect / addSelect / addWhereOpr / setOrder 全て存在
 */
class HxrvPinApi extends ACMS_GET
{
    public function get(): string
    {
        // 認証チェック: SUID が null/0 = 未ログイン
        if (!defined('SUID') || !SUID) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action  = isset($_GET['action'])   ? trim($_GET['action'])   : 'list';
        $pageUrl = isset($_GET['page_url']) ? trim($_GET['page_url']) : '';

        if (empty($pageUrl)) {
            $this->jsonResponse(['success' => false, 'error' => 'page_url is required']);
        }

        switch ($action) {
            case 'export':
                $this->handleExport($pageUrl);
                break;
            case 'list':
            default:
                $this->handleList($pageUrl);
                break;
        }

        return ''; // die()するので到達しない
    }

    // -------------------------------------------------------------------------
    // ピン一覧
    // -------------------------------------------------------------------------

    private function handleList(string $pageUrl): void
    {
        $SQL = SQL::newSelect('hxrv_pins');
        $SQL->addSelect('*');
        $SQL->addWhereOpr('pin_page_url', $pageUrl);
        $SQL->setOrder('pin_created_at', 'ASC');

        $all = DB::query($SQL->get(dsn()), 'all');

        $this->jsonResponse([
            'success' => true,
            'pins'    => $all ?: [],
        ]);
    }

    // -------------------------------------------------------------------------
    // AI向けMarkdownエクスポート
    // -------------------------------------------------------------------------

    private function handleExport(string $pageUrl): void
    {
        $SQL = SQL::newSelect('hxrv_pins');
        $SQL->addSelect('*');
        $SQL->addWhereOpr('pin_page_url', $pageUrl);
        $SQL->addWhereOpr('pin_status', 'resolved', '!=');
        $SQL->setOrder('pin_created_at', 'ASC');

        $pins = DB::query($SQL->get(dsn()), 'all');

        $this->jsonResponse([
            'success'  => true,
            'markdown' => $this->buildMarkdown($pageUrl, $pins ?: []),
        ]);
    }

    // -------------------------------------------------------------------------
    // AIエージェント向けMarkdown組み立て
    // -------------------------------------------------------------------------

    private function buildMarkdown(string $pageUrl, array $pins): string
    {
        $lines   = [];
        $lines[] = '# HXRV Visual Review Export';
        $lines[] = '';
        $lines[] = "**Page:** `{$pageUrl}`";
        $lines[] = '**Exported:** ' . date('Y-m-d H:i:s');
        $lines[] = '**Open pins:** ' . count($pins);
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        if (empty($pins)) {
            $lines[] = '_No open pins on this page._';
            return implode("\n", $lines);
        }

        foreach ($pins as $i => $pin) {
            $num      = $i + 1;
            $selector = $pin['pin_selector']     ?? '(no selector)';
            $comment  = $pin['pin_comment']      ?? '';
            $author   = $pin['pin_author']        ?? '匿名';
            $excerpt  = $pin['pin_text_excerpt']  ?? '';
            $date     = $pin['pin_created_at']    ?? '';
            $ox       = $pin['pin_offset_x']      ?? 0;
            $oy       = $pin['pin_offset_y']      ?? 0;

            $lines[] = "## Pin #{$num}";
            $lines[] = '';
            $lines[] = "**Selector:** `{$selector}`";
            $lines[] = "**Position:** x={$ox}%, y={$oy}%";
            if ($excerpt) {
                $lines[] = "**Text excerpt (120chars):** `{$excerpt}`";
            }
            $lines[] = "**Author:** {$author}";
            $lines[] = "**Date:** {$date}";
            $lines[] = '';
            $lines[] = '**Comment:**';
            $lines[] = $comment;
            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
        }

        $lines[] = '<!-- HXRV: Use the selector to locate each element, then apply the requested change. -->';

        return implode("\n", $lines);
    }

    // -------------------------------------------------------------------------
    // ユーティリティ
    // -------------------------------------------------------------------------

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        die();
    }
}
