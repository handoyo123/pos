<?php

namespace App\Models;

use Trebol\Entrust\Contracts\EntrustPermissionInterface;
use Trebol\Entrust\Traits\EntrustPermissionTrait;

/**
 * @method static where(string $string, mixed|string $name)
 */
class Permission extends BaseModel implements EntrustPermissionInterface
{
    use EntrustPermissionTrait;

    protected $table = 'permissions';

    protected $default = ['xid'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['id', 'pivot'];

    protected $appends = ['xid'];
}
