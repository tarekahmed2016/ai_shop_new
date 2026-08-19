<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable([
    'name_ar',
    'name_en',
    'phone',
    'email',
    'hero_title_ar',
    'hero_title_en',
    'hero_description_ar',
    'hero_description_en',
    'about_ar',
    'about_en',
    'vision_ar',
    'vision_en',
    'mission_ar',
    'mission_en',
    'address_ar',
    'address_en',
    'website',
    'facebook',
    'instagram',
    'linkedin',
    'x_twitter',
    'youtube',
    'tiktok',
    'snapchat',
    'whatsapp',
    'theme_primary_color',
    'theme_dark_color',
    'theme_heading_text_color',
    'theme_body_text_color',
    'theme_muted_text_color',
    'theme_nav_text_color',
    'theme_nav_hover_text_color',
    'theme_hero_text_color',
    'theme_on_dark_text_color',
    'custom_css',
    'custom_js',
])]
class CompanyInfo extends Model
{
    protected $table = 'company_info';

    /**
     * @return MorphOne<Attachment, $this>
     */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
