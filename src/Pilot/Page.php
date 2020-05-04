<?php

namespace Flex360\Pilot\Pilot;

use Auth;
use Illuminate\Support\Str;
use Spatie\Image\Manipulations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Flex360\Pilot\Pilot\Traits\TypeableTrait;
use Flex360\Pilot\Pilot\Traits\UserHtmlTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;
use Flex360\Pilot\Pilot\Traits\SocialMetadataTrait;
use Spatie\MediaLibrary\Models\Media as SpatieMedia;
use Flex360\Pilot\Pilot\Traits\HasEmptyStringAttributes;

class Page extends Model implements HasMedia
{
    use TypeableTrait,
        UserHtmlTrait,
        SocialMetadataTrait,
        HasMediaTrait,
        SoftDeletes,
        HasEmptyStringAttributes;

    public static $current = null;

    protected $table = 'pages';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $appends = [];

    protected $html = ['body'];

    protected $emptyStrings = [
        'body', 'meta_title', 'meta_description', 'breadcrumb', 'link',
        'password', 'block_1', 'block_2', 'settings'
    ];

    public $settingsConfig = [
        // 'adjuggler_placement' => [
        //     'type' => 'text',
        //     'label' => 'AdJuggler Placement ID',
        // ],

        // 'publish_tag' => [
        //     'type' => 'text',
        //     'label' => 'Publish Tag ID',
        // ],
    ];

    public static function boot()
    {
        parent::boot();

        Page::saving(function ($page) {
            // check to make sure parent is not itself
            if ($page->parent_id == $page->id) {
                $page->parent_id = $page->getOriginal('parent_id');
            }

            // set site for page
            $site = Site::getCurrent();
            $page->site_id = isset($site->id) ? $site->id : null;

            // if slug has changed, regenerate paths
            $dirty = $page->getDirty();
            if (isset($dirty['slug'])) {
                Page::saved(function ($page) {
                    $descendants = $page->getDescendants();
                    foreach ($descendants as $descendant) {
                        $descendant->path = $descendant->getPath();
                        $descendant->save();
                    }
                });
            }

            // set slug if empty
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }

            // set status
            if (empty($page->status)) {
                $page->status = 'publish';
            }

            // set page type
            if (empty($page->type_id)) {
                $page->type_id = 1;
            }

            // regenerate the path
            $page->path = $page->getPath();

            // clear cache of the navigation
            Cache::forget('site-nav-' . $site->id);
            Cache::forget('site-nav-view-' . $site->id);

            // clear the page root cache just in case
            Cache::forget('page-root');
        });

        Page::deleted(function () {
            // clear cache of the navigation
            $site = Site::getCurrent();
            Cache::forget('site-nav-' . $site->id);
            Cache::forget('site-nav-view-' . $site->id);
        });
    }

    public function menus()
    {
        return $this->belongsToMany('Flex360\Pilot\Pilot\Menu');
    }

    public function blocks()
    {
        return $this->hasMany('Flex360\Pilot\Pilot\Block')->orderBy('position');
    }

    public function type()
    {
        return $this->belongsTo('Flex360\Pilot\Pilot\PageType', 'type_id');
    }

    public static function getRoot()
    {
        $site = Site::getCurrent();

        $root = Cache::rememberForever('page-root', function () use ($site) {
            return Page::where('site_id', '=', $site->id)->where('level', '=', 0)->first();
        });

        return empty($root) ? new Page : $root;
    }

    public static function getAdminRoot()
    {
        $site = Site::getCurrent();

        // determine root based on role

        // $root = Page::where('site_id', '=', $site->id)->where('id', '=', 3)->first();

        $root = Page::where('site_id', '=', $site->id)->where('level', '=', 0)->first();

        return empty($root) ? new Page : $root;
    }

    public function initRoot()
    {
        if (!$this->exists) {
            $site = Site::getCurrent();

            $this->title = 'Home';
            $this->slug = '/';
            $this->site_id = $site->id;
            $this->path = '/';
            $this->level = 0;
            $this->layout = 'layouts.master';
            $this->save();
        }

        return $this;
    }

    public function url()
    {
        $site = Site::getCurrent();
        if ($this->isRedirect()) {
            $link = $this->link;
            if ($link[0] == '/') {
                $link = $site->getDefaultProtocol() . '://' . $site->getFullDomain() . $link;
            }
            return $link;
        }
        return $site->getDefaultProtocol() . '://' . $site->getFullDomain() . $this->path;
    }

    public function belongsOnSiteMap()
    {
        //Does link start with # sign or if page is Hidden
        if (substr($this->link, 0, 1) == '#' || $this->status == 'hidden') {
            return false;
        } else {
            return true;
        }
    }

    public function getLink($attributes = [])
    {
        $attributeString = '';

        foreach ($attributes as $key => $value) {
            $attributeString .= " $key=\"$value\"";
        }

        return '<a href="' . $this->url() . '"' . $attributeString . '>' . $this->title . '</a>';
    }

    public function getMetaTitle()
    {
        return empty($this->meta_title) ? $this->title : $this->meta_title;
    }

    public function getMetaDesc()
    {
        return empty($this->meta_description) ? $this->getMetaTitle() : Str::limit($this->meta_description, 120, '...');
    }

    public function block($index = 1)
    {
        ob_start();

        //extract($this->vars, EXTR_SKIP);
        eval('use App\Pilot\Publish; ?>' . $this->{'block_' . $index});

        $content = ob_get_clean();
        return $content;
    }

    /**
     * Decodes the JSON string stored in settings property
     * @param string $value
     * @return array
     */
    public function getSettingsAttribute($value)
    {
        return json_decode($value, true);
    }

    /**
     * Converts the settings property to a JSON string when set
     * @param array $value
     */
    public function setSettingsAttribute($value)
    {
        $this->attributes['settings'] = json_encode($value);
    }

    /**
     * Get setting from setting store
     * @param string $key
     * @param string $default
     * @return mixed
     */
    public function getSetting($key, $default = null)
    {
        $value = isset($this->settings[$key]) ? $this->settings[$key] : null;

        if (empty($value)) {
            return $default;
        } else {
            return $value;
        }
    }

    /**
     * Pull in outside content to the page
     * @return Page
     */
    public function populate()
    {
        // check for Publish content
        $tag_id = $this->getSetting('publish_tag');

        if (!empty($tag_id)) {
            $this->body .= Publish\Article::index(['tags' => $tag_id]);
        }

        return $this;
    }

    // functions that support page tree

    public function hasChildren($status = 'all')
    {
        $site = Site::getCurrent();

        $query = Page::where('site_id', '=', $site->id)->where('parent_id', '=', $this->id);

        if ($status != 'all') {
            $query = $query->where('status', $status);
        }

        $childCount = $query->count();

        return $childCount > 0;
    }

    public function getChildren($status = 'all')
    {
        $site = Site::getCurrent();

        $pages = Page::where('site_id', '=', $site->id)
                ->where('parent_id', '=', $this->id)
                ->orderBy('position', 'ASC')
                ->orderBy('id', 'ASC');

        if ($status != 'all') {
            $pages = $pages->where('status', $status);
        }

        return $pages->get();
    }

    public function getDescendants(&$descendants = [])
    {
        foreach ($this->getChildren() as $child) {
            $descendants[] = $child;
            $descendants = $child->getDescendants($descendants);
        }

        return $descendants;
    }

    public function getAncestors()
    {
        $ancestors = [];

        $parent = $this->getParent();

        while (!empty($parent)) {
            $ancestors[] = $parent;

            $parent = $parent->getParent();
        };

        // include root page if ancestors is empty
        if (empty($ancestors)) {
            $ancestors[] = Page::getRoot();
        }

        return $ancestors;
    }

    public function getBreadcrumbs()
    {
        $crumbs = array_reverse($this->getAncestors());

        return $crumbs;
    }

    public function breadcrumbs()
    {
        $page = $this;

        $breadcrumbs = $this->getBreadcrumbs();

        return view('partials.breadcrumbs', compact('page', 'breadcrumbs'));
    }

    public function renderTree()
    {
        $html = null;
        $children = $this->getChildren();

        foreach ($children as $child) {
            $children_html = $child->renderTree();

            $html .= view('pilot::admin.pages.tree', ['page' => $child, 'children' => $children_html])->render();
        }

        return $html;
    }

    public function getTree($status = 'all')
    {
        $nodes = [];

        foreach ($this->getChildren($status) as $child) {
            $nodes[] = $child->getTree($status);
        }

        $container = new PageContainer($this);
        $container->children = $nodes;

        return $container;
    }

    public static function getNav($status = 'publish')
    {
        $site = Site::getCurrent();
        $root = self::getRoot();

        $navClosure = function () use ($root, $site, $status) {
            $pages = $root->getTree($status);

            return view('pilot::nav', compact('pages'))->render();
        };

        if (env('APP_ENV') == 'production') {
            $nav = Cache::rememberForever('site-nav-view-' . $site->id, $navClosure);
        } else {
            $nav = call_user_func($navClosure, $root, $site, $status);
        }

        return $nav;
    }

    public function getParent()
    {
        if (!isset($this->parent_id) || empty($this->parent_id)) {
            return null;
        } else {
            return Page::find($this->parent_id);
        }
    }

    public function getPath($path = null)
    {
        if ($this->isRoot()) {
            return $path;
        }

        $path = '/' . $this->slug . $path;
        $parent = $this->getParent();

        if (is_null($parent)) {
            return $path;
        } else {
            return $parent->getPath($path);
        }
    }

    public function isRoot()
    {
        return is_null($this->parent_id) && $this->level == 0;
    }

    public function isRedirect()
    {
        return !empty($this->link);
    }

    public function getStatus()
    {
        if ($this->isRedirect()) {
            return 'redirect';
        }

        return $this->status;
    }

    /**
     * Reorder pages based on order of page ids passed
     *
     * @param Array $ids
     */
    public static function reorder($ids)
    {
        foreach ($ids as $position => $id) {
            $page = self::find($id);
            $page->position = $position;
            $page->save();
        }
    }

    /**
     * Get a list of all pages and their levels
     *
     * @param Array $list
     * @param int $level
     *
     * @return Array
     */
    public function getList(&$list = [], $level = 0)
    {
        $level++;
        $list[] = ['level' => $level, 'page' => $this];

        foreach ($this->getChildren() as $child) {
            $list = $child->getList($list, $level);
        }

        return $list;
    }

    /**
     * Convert list into list suitable for generating a select box
     *
     * @return Array
     */
    public static function selectList($includeEmpty = false)
    {
        $root = self::getRoot();
        $list = $root->getList();

        $select = [];

        if ($includeEmpty) {
            $select[''] = '[ Choose Page ]';
        }

        foreach ($list as $item) {
            $select[$item['page']->id] = str_repeat('--', $item['level'] - 1) . $item['page']->title;
        }

        return $select;
    }

    /**
     * Get available page layouts
     *
     * @return Array
     */
    public function getLayoutList()
    {
        // get a list of all the layouts
        $layouts = File::files(base_path('vendor/flex360/pilot/resources/views/layouts'));

        // clean up paths
        foreach ($layouts as $index => $layout) {
            // manipulate the path into a blade friendly form
            $layout = str_replace(base_path('vendor/flex360/pilot/resources/views/'), '', $layout);

            $layout = str_replace('.blade.php', '', $layout);

            $layout = str_replace('.php', '', $layout);

            $layout = str_replace('/', '.', $layout);

            // remove the old layout
            unset($layouts[$index]);

            // add the layout in a select list friendly format
            $layouts[$layout] = ucfirst(substr($layout, strrpos($layout, '.') + 1));

            // remove any ignored layouts
            $ignoredLayouts = ['layouts.template', 'layouts.master'];

            // ignore home layout when not the root page
            if (!$this->isRoot()) {
                $ignoredLayouts[] = 'layouts.home';
            }

            if (in_array($layout, $ignoredLayouts)) {
                unset($layouts[$layout]);
            }
        }

        return $layouts;
    }

    /**
     * Get a blade friendly path to the page layout
     *
     * @return String
     */
    public function getLayout()
    {
        return !empty($this->layout) ? $this->layout : config('pilot.default_layout');
    }

    public static function getStatusList()
    {
        return $statusList[] = [
            'publish' => 'Publish',
            'draft' => 'Draft',
            'hidden' => 'Hidden'
        ];
    }

    public static function findByPath($path)
    {
        return static::where('site_id', '=', Site::getCurrent()->id)
                    ->where('path', $path)
                    ->first();
    }

    public static function mimic($data = [], $override = false, $overrideType = 'replace')
    {
        // account for only passing the title
        if (is_string($data)) {
            $data = ['title' => $data];
        }

        // handle specifying a page by id
        if (is_numeric($data)) {
            $page = Page::find($data);
        } else {
            // otherwise, find by the current path
            $page = Page::findByPath('/' . \Request::instance()->path());
        }

        $page = Page::findByPath('/' . \Request::instance()->path());

        if (empty($page) || $override) {
            if ($overrideType == 'merge') {
                $oldData = $page->toArray();
                $data = array_merge($oldData, $data);
            }

            $newPage = new Page;
            $newPage->fill($data);
            $page = $newPage;
        }

        view()->share('page', $page);

        // set the page so we don't need to query for it again
        config(['page' => $page]);

        return $page;
    }

    public function getNavTitle()
    {
        if (!empty($this->breadcrumb)) {
            return $this->breadcrumb;
        }

        return $this->title;
    }

    public static function getCurrent()
    {
        // if guest and we have already found the page-header
        // just return it
        if (Auth::guest() && !empty(self::$current)) {
            return self::$current;
        }

        $path = '/' . request()->path();

        $query = Page::where('site_id', '=', Site::getCurrent()->id)
                    ->where('path', 'LIKE', $path);

        // account for page status for non-admin users
        if (Auth::check() && Auth::user()->isAdmin()) {
            // any status is fine
        } else {
            $query = $query->whereIn('status', ['publish', 'hidden']);
        }

        $page = $query->first();

        if (Auth::guest()) {
            self::$current = $page;
        }

        return $page;
    }

    public function registerMediaConversions(SpatieMedia $media = null)
    {
        // let's always use standard names like thumb, xsmall, small, medium, large, xlarge

        $this->addMediaConversion('thumb')
             ->crop(Manipulations::CROP_TOP_RIGHT, 300, 300);

        $this->addMediaConversion('small')
             ->width(300)
             ->height(300);
    }

    public function getFeaturedImageAttribute($value)
    {
        $mediaItem = $this->getFirstMedia('featured_image');

        if (!empty($mediaItem)) {
            return $mediaItem->getUrl();
        }

        return $value;
    }
}
