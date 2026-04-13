<?php

namespace Tests\Unit;

use App\Services\GeoService;
use PHPUnit\Framework\TestCase;

class GeoServiceTest extends TestCase
{
    private GeoService $geo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geo = new GeoService();
    }

    public function test_haversine_returns_zero_for_identical_points(): void
    {
        $this->assertEqualsWithDelta(
            0.0,
            $this->geo->haversineKm(14.7167, -17.4677, 14.7167, -17.4677),
            0.001
        );
    }

    public function test_haversine_dakar_to_nouakchott_is_about_407_km(): void
    {
        // Dakar (14.7167, -17.4677) → Nouakchott (18.0858, -15.9785)
        // Great-circle (straight-line) distance ≈ 407 km. Road distance
        // is ~488 km but we only care about the sphere-accurate value here.
        $distance = $this->geo->haversineKm(14.7167, -17.4677, 18.0858, -15.9785);

        $this->assertEqualsWithDelta(407, $distance, 3);
    }

    public function test_haversine_is_symmetric(): void
    {
        $a = $this->geo->haversineKm(14.7167, -17.4677, 18.0858, -15.9785);
        $b = $this->geo->haversineKm(18.0858, -15.9785, 14.7167, -17.4677);

        $this->assertEqualsWithDelta($a, $b, 0.0001);
    }

    public function test_haversine_short_distance_matches_pythagoras(): void
    {
        // Two points ~1 km apart near the equator. For short distances
        // haversine ≈ sqrt(dlat² + dlng²) * 111 km per degree.
        $distance = $this->geo->haversineKm(0.0, 0.0, 0.0, 0.009);

        // 0.009° longitude at equator ≈ 1.001 km
        $this->assertEqualsWithDelta(1.001, $distance, 0.01);
    }

    public function test_midpoint_returns_average_of_coordinates(): void
    {
        $mid = $this->geo->midpoint(14.0, -17.0, 18.0, -15.0);

        $this->assertEqualsWithDelta(16.0, $mid['latitude'], 0.0001);
        $this->assertEqualsWithDelta(-16.0, $mid['longitude'], 0.0001);
    }

    public function test_haversine_metres_for_close_points(): void
    {
        // Two points ~111 metres apart (0.001° lat at any longitude)
        $distanceMetres = $this->geo->haversineMetres(14.7167, -17.4677, 14.7177, -17.4677);

        $this->assertEqualsWithDelta(111, $distanceMetres, 1);
    }
}
