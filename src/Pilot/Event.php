<?php

namespace Flex360\Pilot\Pilot;

use Illuminate\Support\Str;
use Flex360\Pilot\Pilot\Tag;
use Spatie\Image\Manipulations;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Flex360\Pilot\Scopes\ActiveEventScope;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Flex360\Pilot\Pilot\Traits\UserHtmlTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;
use Flex360\Pilot\Pilot\Traits\PresentableTrait;
use Flex360\Pilot\Pilot\Traits\HasEmptyStringAttributes;
use Spatie\MediaLibrary\HasMedia\Interfaces\HasMediaConversions;

class Event extends Model implements HasMedia
{
    use PresentableTrait,
        UserHtmlTrait,
        HasMediaTrait,
        SoftDeletes,
        HasEmptyStringAttributes;

    protected $table = 'events';

    protected $guarded = array('id', 'created_at', 'updated_at');

    protected $html = ['body'];

    protected $emptyStrings = ['title', 'body', 'short_description', 'gallery', 'image'];

    public function getDates()
    {
        return array('created_at', 'updated_at', 'start', 'end', 'published_at');
    }

    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(new ActiveEventScope);

        Event::saving(function ($event) {
            // reformat start and end date
            $event->start = date('Y-m-d H:i:s', strtotime($event->start));
            $event->end = date('Y-m-d H:i:s', strtotime($event->end));
        });
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Formats the start date when it is accessed
     * @param string $value
     * @return string
     */
    public function getStartAttribute($value)
    {
        return \Carbon\Carbon::createFromTimeStamp(strtotime($value));
    }

    /**
     * Converts the start date when set
     * @param string $value
     */
    public function setStartAttribute($value)
    {
        $this->attributes['start'] = date('Y-m-d H:i:s', strtotime($value));
    }

    /**
     * Formats the end date when it is accessed
     * @param string $value
     * @return string
     */
    public function getEndAttribute($value)
    {
        return \Carbon\Carbon::createFromTimeStamp(strtotime($value));
    }

    /**
     * Converts the end date when set
     * @param string $value
     */
    public function setEndAttribute($value)
    {
        $this->attributes['end'] = date('Y-m-d H:i:s', strtotime($value));
    }

    /**
     * Formats the end date when it is accessed
     * @param string $value
     * @return string
     */
    public function getPublishedAtAttribute($value)
    {
        return \Carbon\Carbon::createFromTimeStamp(strtotime($value));
    }

    /**
     * Converts the end date when set
     * @param string $value
     */
    public function setPublishedAtAttribute($value)
    {
        $this->attributes['published_at'] = date('Y-m-d H:i:s', strtotime($value));
    }

    /**
     * Converts the gallery property to a JSON string when set
     * @param array $value
     */
    public function setGalleryAttribute($value)
    {
        $this->attributes['gallery'] = serialize($value);
    }

    public function addTags($tags)
    {
        // convert an non numeric tags into real tags
        foreach ($tags as $index => $tag) {
            if (! is_numeric($tag)) {
                $newTag = Tag::create(['name' => $tag]);
                $tags[$index] = $newTag->id;
            }
        }

        $this->tags()->sync($tags);
    }

    public function link()
    {
        return '<a href="' . $this->url() . '">' . $this->title . '</a>';
    }

    public function url()
    {
        return route('calendar.event', [
            'id' => $this->id,
            'slug' => !empty($this->title) ? Str::slug($this->title) : 'event'
        ]);
    }

    public static function getStatuses()
    {
        return [
            10 => 'Draft',
            30 => 'Published'
        ];
    }

    public function getStatus()
    {
        $statuses = static::getStatuses();

        // return an empty object if no status is found
        if (! isset($statuses[$this->status])) {
            return (object) [
                'id' => '',
                'name' => '',
            ];
        }

        if ($this->status == 30 && strtotime($this->published_at) > time()) {
            return (object) [
                'id' => $this->status,
                'name' => 'Scheduled',
            ];
        }

        return (object) [
            'id' => $this->status,
            'name' => $statuses[$this->status],
        ];
    }

    /**
     * Scope a query to only draft events.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 10);
    }

    /**
     * Scope a query to only scheduled events.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 30)
                    ->whereRaw('published_at > NOW()');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 30)
                    ->whereRaw('published_at <= NOW()');
    }

    public function scopePast($query)
    {
        return $query->whereRaw('end <= NOW()');
    }

    public function duplicate()
    {
        $model = $this;

        $model->load('tags');

        $newModel = $model->replicate();

        // append to the title to designate a copy
        $newModel->title .= ' (Copy)';

        // make new copy a draft
        $newModel->status = 10;

        $newModel->push();

        // add tags
        foreach ($model->tags as $tag) {
            $newModel->tags()->attach($tag->id);
        }

        return $newModel;
    }

    public static function filter($query, $data = [])
    {
        if (empty($data)) {
            $data = request()->all();
        }

        // filter by keyword
        if (isset($data['keyword'])) {
            $query = $query->where('title', 'like', '%' . $data['keyword'] . '%');
        }

        return $query;
    }

    public function displayDate()
    {
        $startFormat = 'n/j/Y g:i a';

        $endFormat = 'n/j/Y g:i a';

        $separator = ' - ';

        // $start = \Carbon\Carbon::createFromTimeStamp(strtotime($this->start));
        //
        // $end = \Carbon\Carbon::createFromTimeStamp(strtotime($this->end));

        // if start and end dates are the same, only show time of end date
        if ($this->start->toDateString() == $this->end->toDateString()) {
            $startFormat = 'n/j/Y g:i a';
            $endFormat = 'g:i a';
        }

        // if times are 24 hours apart
        if ($this->start->toTimeString() == '00:00:00' && $this->end->toTimeString() == '23:59:00') {
            // if they are different dates
            if ($this->start->toDateString() != $this->end->toDateString()) {
                $startFormat = 'n/j/Y';

                $endFormat = 'n/j/Y';
            } else { // if they are the same dates
                $startFormat = 'n/j/Y';

                $endFormat = '';

                $separator = null;
            }
        }

        // if end time is 11:59pm but start is not 12am
        if ($this->start->toTimeString() != '00:00:00' && $this->end->toTimeString() == '23:59:00') {
            // if they are the same day
            if ($this->start->toDateString() == $this->end->toDateString()) {
                $startFormat = 'n/j/Y g:i a';

                $endFormat = '';

                $separator = null;
            }
        }

        return $this->start->format($startFormat) . $separator . $this->end->format($endFormat);
    }

    public function registerMediaConversions(Media $media = null)
    {
        // let's always use standard names like thumb, xsmall, small, medium, large, xlarge

        $this->addMediaConversion('thumb')
             ->crop(Manipulations::CROP_TOP_RIGHT, 300, 300);

        $this->addMediaConversion('small')
             ->width(300)
             ->height(300);
    }

    public function getImageAttribute($value)
    {
        $mediaItem = $this->getFirstMedia('image');

        if (! empty($mediaItem)) {
            return $mediaItem->getUrl();
        }

        return $value;
    }

    public function hasImage()
    {
        return ! empty($this->image);
    }

    public function getGalleryAttribute($value)
    {
        $mediaItems = $this->getMedia('gallery');

        if ($mediaItems->isEmpty()) {
            $array = unserialize($value);

            return is_array($array) ? $array : [];
        }

        return $mediaItems->transform(function ($item, $key) {
            return [
                'path' => $item->getUrl(),
                'title' => $item->getCustomProperty('title'),
                'caption' => $item->getCustomProperty('description'),
                'extra' => '',
            ];
        })->toArray();
    }

    public function hasGallery()
    {
        return ! empty($this->gallery);
    }
}
