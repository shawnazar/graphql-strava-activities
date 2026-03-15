<?php
/**
 * WPGraphQL function stubs for PHPStan.
 *
 * @package WPGraphQL\Strava
 */

/**
 * @param string               $type_name Type name.
 * @param array<string, mixed> $config    Type configuration.
 * @return void
 */
function register_graphql_object_type( string $type_name, array $config ): void {}

/**
 * @param string               $type_name  Type to extend.
 * @param string               $field_name Field name.
 * @param array<string, mixed> $config     Field configuration.
 * @return void
 */
function register_graphql_field( string $type_name, string $field_name, array $config ): void {}
