<?php

declare(strict_types=1);

namespace KC\EloquentViewable;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use Jenssegers\Mongodb\Eloquent\Builder;
use KC\EloquentViewable\Contracts\View as ViewContract;
use KC\EloquentViewable\Contracts\Viewable;
use KC\EloquentViewable\Contracts\Views as ViewsContract;
use KC\EloquentViewable\Contracts\Visitor as VisitorContract;
use KC\EloquentViewable\Events\ViewRecorded;
use KC\EloquentViewable\Exceptions\ViewRecordException;
use KC\EloquentViewable\Support\Period;

class Views implements ViewsContract
{
    use Macroable;

    protected Viewable $viewable;

    protected ?Period $period = null;

    protected bool $unique = false;

    protected ?DateTimeInterface $cooldown = null;

    protected ?string $collection = null;

    protected ?DateTimeInterface $cacheLifetime = null;

    protected VisitorContract $visitor;

    protected CooldownManager $cooldownManager;

    protected ConfigRepository $config;

    protected CacheRepository $cache;

    public function __construct(
        ConfigRepository $config,
        CacheRepository $cache,
        CooldownManager $cooldownManager,
        VisitorContract $visitor
    )
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->cooldownManager = $cooldownManager;
        $this->visitor = $visitor;
    }

    public function forViewable(Viewable $viewable): ViewsContract
    {
        $this->viewable = $viewable;

        return $this;
    }

    /**
     * Get the views count.
     */
    public function count(): int
    {
        $query = $this->resolveViewableQuery();

        $cacheKey = $this->makeCacheKey($this->period, $this->unique, $this->collection);

        if ($this->shouldCache()) {
            $cachedViewsCount = $this->cache->get($cacheKey);

            // Return cached views count if it exists
            if ($cachedViewsCount !== null) {
                return (int)$cachedViewsCount;
            }
        }

        $query->when($this->period, function ($query, $period) {
            $query->withinPeriod($period);
        });

        $query->when($this->collection, function ($query, $collection) {
            $query->collection($collection);
        });

        $viewsCount = $this->unique ? $query->count(DB::raw('DISTINCT visitor')) : $query->count();

        if ($this->shouldCache() && $this->cacheLifetime !== null) {
            $this->cache->put($cacheKey, $viewsCount, $this->cacheLifetime);
        }

        return $viewsCount;
    }

    /**
     * Record a view for the viewable Eloquent model.
     *
     * @throws \KC\EloquentViewable\Exceptions\ViewRecordException
     */
    public function record(): bool
    {
        if ($this->viewable instanceof Viewable && $this->viewable->getKey() === null) {
            throw ViewRecordException::cannotRecordViewForViewableType();
        }

        if (!$this->shouldRecord()) {
            return false;
        }

        event(new ViewRecorded($view = $this->createView()));

        return $view->exists;
    }

    /**
     * Destroy all views of the viewable model.
     */
    public function destroy(): void
    {
        $this->resolveViewableQuery()->delete();
    }

    /**
     * Set the cooldown.
     *
     * @param \DateTimeInterface|int|null $cooldown
     */
    public function cooldown($cooldown): ViewsContract
    {
        if (is_int($cooldown)) {
            $cooldown = Carbon::now()->addMinutes($cooldown);
        }

        if ($cooldown instanceof DateTimeInterface) {
            $cooldown = Carbon::instance($cooldown);
        }

        $this->cooldown = $cooldown;

        return $this;
    }

    /**
     * Set the period.
     */
    public function period(?Period $period): ViewsContract
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Set the collection.
     */
    public function collection(?string $name): ViewsContract
    {
        $this->collection = $name;

        return $this;
    }

    /**
     * Fetch only unique views.
     */
    public function unique(bool $state = true): ViewsContract
    {
        $this->unique = $state;

        return $this;
    }

    /**
     * Cache the current views count.
     *
     * @param \DateTimeInterface|int|null $lifetime
     */
    public function remember($lifetime = null): ViewsContract
    {
        if ($lifetime !== null) {
            $lifetime = $this->resolveCacheLifetime($lifetime);
        }

        $this->cacheLifetime = $lifetime;

        return $this;
    }

    /**
     * Set the visitor.
     *
     * @param \KC\EloquentViewable\Contracts\Visitor
     */
    public function useVisitor(VisitorContract $visitor): ViewsContract
    {
        $this->visitor = $visitor;

        return $this;
    }

    /**
     * Determine if we should record the view.
     *
     * @return bool
     */
    protected function shouldRecord(): bool
    {
        // If ignore bots is true and the current visitor is a bot, return false
        if ($this->config->get('eloquent-viewable.ignore_bots') && $this->visitor->isCrawler()) {
            return false;
        }

        // If we honor to the DNT header and the current request contains the
        // DNT header, return false
        if ($this->config->get('eloquent-viewable.honor_dnt', false) && $this->visitor->hasDoNotTrackHeader()) {
            return false;
        }

        if (collect($this->config->get('eloquent-viewable.ignored_ip_addresses'))->contains($this->visitor->ip())) {
            return false;
        }

        if ($this->cooldown !== null && !$this->cooldownManager->push($this->viewable, $this->cooldown, $this->collection)) {
            return false;
        }

        return true;
    }

    function get_mongo_date($date)
    {
        if ($date instanceof \MongoDB\BSON\UTCDateTime) {
            return $date;
        } else if ($date instanceof \Carbon\Carbon) {
            return new \MongoDB\BSON\UTCDateTime(new DateTime($date->toDateTimeString()));
        } else {
            return new \MongoDB\BSON\UTCDateTime(new DateTime($date));
        }
    }


    function formatDate($object)
    {
        foreach ($object as $key => $value) {

            try {
                //        Carbon::parse($date);

                if (is_string($value) && strtotime($value)) {
                    // it's in date format
                    $object[$key] = $this->get_mongo_date($value);
                }
                //\Carbon\Exceptions\InvalidFormatException
            } catch (\Exception $e) {
                //      echo 'invalid date, enduser understands the error message';
            }
        }
        return $object;
    }

    /**
     * Create a new view instance.
     *
     * @return \KC\EloquentViewable\Contracts\View
     */
    protected function createView(): ViewContract
    {
        $view = Container::getInstance()->make(ViewContract::class);

        return $view->create([
            'viewable_id' => $this->viewable->getKey(),
            'viewable' => $this->formatDate((array)$this->viewable->toArray()),
            'viewable_type' => $this->viewable->getMorphClass(),
            'visitor' => $this->formatDate((array)$this->visitor->values()),
            'visitor_id' => ($this->visitor->values())['id'],
            'collection' => $this->collection,
            'viewed_at' => new \MongoDB\BSON\UTCDateTime(Carbon::now()),
        ]);
    }

    /**
     * Determine if we should cache the views count.
     *
     * @return bool
     */
    protected function shouldCache(): bool
    {
        return $this->cacheLifetime !== null;
    }

    /**
     * Resolve the viewable query builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function resolveViewableQuery(): Builder
    {
        // If null, we take for granted that we need to count the viewable type
        if ($this->viewable->getKey() === null) {
            $viewableType = $this->viewable->getMorphClass();

            return Container::getInstance()
                ->make(ViewContract::class)
                ->where('viewable_type', $viewableType);
        }

        return $this->viewable->views()->getQuery();
    }

    /**
     * Make a cache key for the viewable with custom query options.
     *
     * @param \KC\EloquentViewable\Support\Period|null $period
     * @param bool $unique
     * @param string|null $collection
     * @return string
     */
    protected function makeCacheKey(?Period $period = null, bool $unique = false, ?string $collection = null): string
    {
        return (CacheKey::fromViewable($this->viewable))->make($period, $unique, $collection);
    }

    /**
     * Resolve cache lifetime.
     *
     * @param \Carbon\CarbonInterface|\DateTimeInterface|int
     * @return \Carbon\CarbonInterface
     */
    protected function resolveCacheLifetime($lifetime): CarbonInterface
    {
        if (is_int($lifetime)) {
            return Carbon::now()->addMinutes($lifetime);
        }

        if ($lifetime instanceof CarbonInterface) {
            return $lifetime;
        }

        if ($lifetime instanceof DateTimeInterface) {
            return Carbon::instance($lifetime);
        }

        throw new InvalidArgumentException("Argument $lifetime must be of type int, \Carbon\CarbonInterface or \DateTimeInterface");
    }
}
