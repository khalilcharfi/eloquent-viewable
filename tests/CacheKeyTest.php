<?php

declare(strict_types=1);

namespace KC\EloquentViewable\Tests;

use KC\EloquentViewable\CacheKey;
use KC\EloquentViewable\Support\Period;
use KC\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Support\Facades\Config;

class CacheKeyTest extends TestCase
{
    /** @var \KC\EloquentViewable\Tests\TestClasses\Models\Post */
    protected $post;

    public function setUp(): void
    {
        parent::setUp();

        $this->firstPost = factory(Post::class)->create();
        $this->secondPost = factory(Post::class)->create();

        Config::set('eloquent-viewable.cache.key', 'test-namespace');
    }

    /** @test */
    public function it_can_make_a_key_from_default_parameters()
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.1.|.normal',
            $firstPostCacheKey->make()
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.2.|.normal',
            $secondPostCacheKey->make()
        );
    }

    /** @test */
    public function it_can_make_a_key_from_period_with_startdatetime()
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.1.1553126400|.normal',
            $firstPostCacheKey->make(Period::since('2019-03-21'))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.2.1334275200|.normal',
            $secondPostCacheKey->make(Period::since('2012-04-13'))
        );
    }

    /** @test */
    public function it_can_make_a_key_from_period_with_enddatetime()
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.1.|1593734400.normal',
            $firstPostCacheKey->make(Period::upto('2020-07-03'))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.2.|1726531200.normal',
            $secondPostCacheKey->make(Period::upto('2024-09-17'))
        );
    }

    /** @test */
    public function it_can_make_a_key_from_period_with_past_or_sub_datetimes()
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.1.past2days|.normal',
            $firstPostCacheKey->make(Period::pastDays(2))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.1.sub34seconds|.normal',
            $firstPostCacheKey->make(Period::subSeconds(34))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.2.past3years|.normal',
            $secondPostCacheKey->make(Period::pastYears(3))
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.2.sub3weeks|.normal',
            $secondPostCacheKey->make(Period::subWeeks(3))
        );
    }

    /** @test */
    public function it_can_make_a_key_from_type_unique()
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.1.|.unique',
            $firstPostCacheKey->make(null, true)
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.2.|.unique',
            $secondPostCacheKey->make(null, true)
        );
    }

    /** @test */
    public function it_can_make_a_key_from_collection()
    {
        $firstPostCacheKey = new CacheKey($this->firstPost);
        $secondPostCacheKey = new CacheKey($this->secondPost);

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.1.|.normal.some-collection',
            $firstPostCacheKey->make(null, false, 'some-collection')
        );

        $this->assertEquals(
            'test-namespace:sqlite::memory::posts:khalilcharfieloquentviewableteststestclassesmodelspost.2.|.normal.some-collection',
            $secondPostCacheKey->make(null, false, 'some-collection')
        );
    }
}
