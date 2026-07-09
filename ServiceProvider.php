<?php

namespace Acms\Plugins\HxrvAcms;

use ACMS_App;
use Acms\Services\Common\HookFactory;
use DB;

class ServiceProvider extends ACMS_App
{
    /** @var string */
    public $version = '1.0.0';

    /** @var string */
    public $name = 'HxrvAcms';

    /** @var string */
    public $author = 'youheiokubo';

    /** @var bool */
    public $module = false;

    /**
     * 管理ページ: /bid/{BID}/admin/app_hxrv_api/
     * テンプレート: themes/system/admin/app/hxrv/api.html
     * @var string
     */
    public $menu = 'hxrv_api';

    /** @var string */
    public $desc = 'HXRV AI-Ready Visual Review for a-blog cms';

    // -------------------------------------------------------------------------
    // ライフサイクル
    // -------------------------------------------------------------------------

    public function init(): void
    {
        $hook = HookFactory::singleton();
        $hook->attach('HxrvAcmsHook', new Hook());
    }

    public function checkRequirements(): bool
    {
        return true;
    }

    public function install(): void
    {
        $prefix = DB_PREFIX;
        $table  = $prefix . 'hxrv_pins';

        // テーブルが既に存在する場合はスキップ
        DB::query([
            'sql'    => "DROP TABLE IF EXISTS `{$table}`",
            'params' => [],
        ], 'exec');

        DB::query([
            'sql'    => "
                CREATE TABLE `{$table}` (
                    `pin_id`          int(11)       NOT NULL AUTO_INCREMENT,
                    `pin_blog_id`     int(11)       NULL DEFAULT '1',
                    `pin_page_url`    varchar(2048) NULL,
                    `pin_selector`    varchar(1024) NULL,
                    `pin_offset_x`    float         NULL DEFAULT '50',
                    `pin_offset_y`    float         NULL DEFAULT '50',
                    `pin_text_excerpt` varchar(500)  NULL,
                    `pin_comment`     text          NULL,
                    `pin_author`      varchar(255)  NULL,
                    `pin_status`      varchar(20)   NOT NULL DEFAULT 'open',
                    `pin_created_at`  datetime      NULL,
                    `pin_updated_at`  datetime      NULL,
                    PRIMARY KEY (`pin_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",
            'params' => [],
        ], 'exec');
    }

    public function uninstall(): void
    {
        $table = DB_PREFIX . 'hxrv_pins';
        DB::query([
            'sql'    => "DROP TABLE IF EXISTS `{$table}`",
            'params' => [],
        ], 'exec');
    }

    public function update(): bool
    {
        return true;
    }

    public function activate(): bool
    {
        return true;
    }

    public function deactivate(): bool
    {
        return true;
    }
}
