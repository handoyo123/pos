<?php

namespace App\Models;

class Tax extends BaseModel
{
	protected $table = 'taxes';

	protected $default = ['xid', 'name', 'rate'];

	protected $guarded = ['id', 'created_at', 'updated_at'];

	protected $filterable = ['name'];

	protected $hidden = ['id'];

	protected $appends = ['xid'];
}