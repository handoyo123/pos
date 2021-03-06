<?php

namespace App\Models;

class ExpenseCategory extends BaseModel
{
    protected $table = 'expense_categories';

    protected $default = ['xid', 'name'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['id'];

    protected $appends = ['xid'];

    protected $filterable = ['name'];
}
