<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Cms\Models\Page;
use Illuminate\View\View;

class CmsWebController extends Controller
{
    public function page(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('cms.page', ['page' => $page]);
    }
}
