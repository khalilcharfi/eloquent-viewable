<?php

declare(strict_types=1);

namespace KC\EloquentViewable\Tests;

use KC\EloquentViewable\Contracts\CrawlerDetector;
use KC\EloquentViewable\Visitor;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

class VisitorTest extends TestCase
{
    /** @test */
    public function it_can_get_the_ip_address_from_the_request()
    {
        $this->mock(Request::class, function ($mock) {
            $mock->shouldReceive('ip')->once()->andReturn('241.224.55.106');
        });

        $visitor = Container::getInstance()->make(Visitor::class);

        $this->assertEquals('241.224.55.106', $visitor->ip());
    }

    /** @test */
    public function it_can_determine_if_the_visitor_has_a_do_not_tracker_header_from_the_request()
    {
        $this->mock(Request::class, function ($mock) {
            $mock->shouldReceive('header')->once()->andReturn('1');
        });

        $visitor = Container::getInstance()->make(Visitor::class);

        $this->assertTrue($visitor->hasDoNotTrackHeader());
    }

    /** @test */
    public function it_can_determine_if_the_visitor_is_a_crawler_from_the_crawler_detector()
    {
        $this->mock(CrawlerDetector::class, function ($mock) {
            $mock->shouldReceive('isCrawler')->once()->andReturn(true);
        });

        $visitor = Container::getInstance()->make(Visitor::class);

        $this->assertTrue($visitor->isCrawler());
    }
}
