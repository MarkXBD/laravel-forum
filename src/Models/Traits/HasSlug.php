<?php

namespace Riari\Forum\Models\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Attribute: Slug
     *
     * @return string
     */
    public function getSlugAttribute()
    {
        $slug = Str::slug($this->title);
        
        if (empty($slug)) {
            return 0;
        }
        
        return $slug;
    }
}
