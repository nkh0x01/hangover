<?php

declare(strict_types=1);

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsPage extends Model
{
    use SoftDeletes;

    protected $table = 'cms_pages';

    protected $fillable = ['slug', 'locale', 'title', 'body', 'status', 'published_at', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
