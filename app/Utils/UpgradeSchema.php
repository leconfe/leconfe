<?php

namespace App\Utils;

use App\Utils\UpgradeSchemas\Upgrade110;
use App\Utils\UpgradeSchemas\Upgrade120Beta1;
use App\Utils\UpgradeSchemas\Upgrade120Beta3;
use App\Utils\UpgradeSchemas\Upgrade120Beta4;
use App\Utils\UpgradeSchemas\Upgrade121;
use App\Utils\UpgradeSchemas\Upgrade122;
use App\Utils\UpgradeSchemas\Upgrade125;
use App\Utils\UpgradeSchemas\Upgrade128;
use App\Utils\UpgradeSchemas\Upgrade130Beta1;
use App\Utils\UpgradeSchemas\Upgrade130Beta3;
use App\Utils\UpgradeSchemas\Upgrade130Beta4;
use App\Utils\UpgradeSchemas\Upgrade130Rc1;
use App\Utils\UpgradeSchemas\Upgrade131;
use App\Utils\UpgradeSchemas\Upgrade132;
use App\Utils\UpgradeSchemas\Upgrade133;
use App\Utils\UpgradeSchemas\Upgrade134;
use App\Utils\UpgradeSchemas\UpgradeBeta3;
use App\Utils\UpgradeSchemas\UpgradeBeta4;
use App\Utils\UpgradeSchemas\UpgradeBeta5;

class UpgradeSchema
{
    public static $schemas = [
        '1.0.0-beta.3' => UpgradeBeta3::class,
        '1.0.0-beta.4' => UpgradeBeta4::class,
        '1.0.0-beta.5' => UpgradeBeta5::class,
        '1.1.0' => Upgrade110::class,
        '1.2.0-beta.1' => Upgrade120Beta1::class,
        '1.2.0-beta.3' => Upgrade120Beta3::class,
        '1.2.0-beta.4' => Upgrade120Beta4::class,
        '1.2.1' => Upgrade121::class,
        '1.2.2' => Upgrade122::class,
        '1.2.5' => Upgrade125::class,
        '1.2.8' => Upgrade128::class,
        '1.3.0-beta.1' => Upgrade130Beta1::class,
        '1.3.0-beta.3' => Upgrade130Beta3::class,
        '1.3.0-beta.4' => Upgrade130Beta4::class,
        '1.3.0-rc.1' => Upgrade130Rc1::class,
        '1.3.1' => Upgrade131::class,
        '1.3.2' => Upgrade132::class,
        '1.3.3' => Upgrade133::class,
        '1.3.4' => Upgrade134::class,
    ];

    public static function getSchemasByVersion(string $installedVersion, string $applicationVersion)
    {
        $filteredActions = [];

        foreach (static::$schemas as $upgradeVersion => $upgradeClass) {
            // filter upgrade script by comparing to database version and application version
            if (version_compare($installedVersion, $upgradeVersion, '<') && version_compare($applicationVersion, $upgradeVersion, '>=')) {
                $filteredActions[$upgradeVersion] = new $upgradeClass($installedVersion, $applicationVersion);
            }
        }

        return $filteredActions;
    }
}
