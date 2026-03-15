<?php
/**
 * Unit tests for the SVG route map generator.
 *
 * Uses Brain\Monkey to mock WordPress functions.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

namespace GraphQLStrava\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

// Load dependencies.
require_once dirname( __DIR__, 2 ) . '/includes/polyline.php';

class SvgTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock WordPress functions used by svg.php.
        Functions\stubTranslationFunctions();

        Functions\when( 'get_option' )->alias(
            function ( string $option, $default = '' ) {
                $options = [
                    'wpgraphql_strava_svg_color'        => '#0d9488',
                    'wpgraphql_strava_svg_stroke_width'  => 2.5,
                ];
                return $options[ $option ] ?? $default;
            }
        );

        Functions\when( 'apply_filters' )->alias(
            function ( string $tag, $value ) {
                return $value;
            }
        );

        Functions\when( 'esc_attr' )->alias(
            function ( string $text ) {
                return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
            }
        );

        Functions\when( 'esc_attr__' )->alias(
            function ( string $text ) {
                return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
            }
        );

        // Now load svg.php (after mocks are set up).
        require_once dirname( __DIR__, 2 ) . '/includes/svg.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_empty_polyline_returns_empty_string(): void {
        $this->assertSame( '', wpgraphql_strava_polyline_to_svg( '' ) );
    }

    public function test_returns_valid_svg_element(): void {
        $svg = wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC' );

        $this->assertStringStartsWith( '<svg', $svg );
        $this->assertStringEndsWith( '</svg>', $svg );
        $this->assertStringContainsString( 'xmlns="http://www.w3.org/2000/svg"', $svg );
    }

    public function test_svg_contains_path_element(): void {
        $svg = wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC' );

        $this->assertStringContainsString( '<path', $svg );
        $this->assertStringContainsString( 'fill="none"', $svg );
    }

    public function test_svg_uses_default_dimensions(): void {
        $svg = wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC' );

        $this->assertStringContainsString( 'viewBox="0 0 300 200"', $svg );
        $this->assertStringContainsString( 'width="300"', $svg );
        $this->assertStringContainsString( 'height="200"', $svg );
    }

    public function test_svg_uses_custom_dimensions(): void {
        $svg = wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC', 500, 400 );

        $this->assertStringContainsString( 'viewBox="0 0 500 400"', $svg );
        $this->assertStringContainsString( 'width="500"', $svg );
        $this->assertStringContainsString( 'height="400"', $svg );
    }

    public function test_svg_uses_default_stroke_color(): void {
        $svg = wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC' );

        $this->assertStringContainsString( 'stroke="#0d9488"', $svg );
    }

    public function test_svg_uses_custom_stroke_color(): void {
        $svg = wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC', 300, 200, '#ff0000' );

        $this->assertStringContainsString( 'stroke="#ff0000"', $svg );
    }

    public function test_svg_has_accessibility_attributes(): void {
        $svg = wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC' );

        $this->assertStringContainsString( 'role="img"', $svg );
        $this->assertStringContainsString( 'aria-label=', $svg );
    }

    public function test_svg_path_starts_with_move_command(): void {
        $svg = wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC' );

        // Extract the path d attribute.
        preg_match( '/d="(M[^"]+)"/', $svg, $matches );
        $this->assertNotEmpty( $matches, 'SVG path should have a d attribute starting with M' );
        $this->assertStringStartsWith( 'M', $matches[1] );
        $this->assertStringContainsString( 'L', $matches[1] );
    }
}
