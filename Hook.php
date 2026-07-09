<?php

namespace Acms\Plugins\HxrvAcms;

use ACMS_RAM;

/**
 * Hook.php
 *
 * グローバル変数 {HXRV_ACTIVE} を提供する。
 * テーマ側で「ログイン中だけオーバーレイを出す」出し分けに使える。
 *
 *   <!-- BEGIN_IF [{HXRV_ACTIVE}/eq/1] -->
 *   @include("/include/hxrv-overlay.html")
 *   <!-- END_IF -->
 *
 * ※ hxrv-overlay.html 内のCSS/JS/APIパスは、テンプレート変数ではなく
 *    ファイル内の【設定】ブロックで直接指定する方式（環境非依存で確実）。
 *    そのため ASSET_URL 等の変数はここでは提供していない。
 *
 * 確認済み（a-blog cms 3.2.26）:
 *   SUID       = ログイン中の管理ユーザーID（未ログインは null）
 *   PLUGIN_DIR = /extension/plugins/（URLパス）
 */
class Hook
{
    /**
     * @param \Field $globalVars
     */
    public function extendsGlobalVars(&$globalVars): void
    {
        // SUID が null/0 = 未ログイン
        if (!defined('SUID') || !SUID) {
            $globalVars->set('HXRV_ACTIVE', '0');
            return;
        }

        $globalVars->set('HXRV_ACTIVE', '1');
    }

    /**
     * ログイン中ユーザー名（現状テンプレートからは未使用。将来用に残す）
     */
    public function getUserName(): string
    {
        if (!defined('SUID') || !SUID) {
            return '';
        }
        return ACMS_RAM::userName(SUID) ?: '匿名';
    }
}
