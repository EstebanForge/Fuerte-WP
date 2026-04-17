<?php

use PHPUnit\Framework\TestCase;

/**
 * Blocked Updates Integration Tests
 *
 * @since 1.9.0
 * Tests the complete flow from config to filter execution for blocked updates
 */
class BlockedUpdatesIntegrationTest extends TestCase
{
    /**
     * @var array
     */
    private $originalOptions;

    protected function setUp(): void
    {
        parent::setUp();

        // Store original options
        global $wp_tests_options;
        $this->originalOptions = $wp_tests_options ?? [];

        // Clear any existing cache
        Fuerte_Wp_Config::invalidate_cache();
    }

    protected function tearDown(): void
    {
        // Restore original options
        global $wp_tests_options;
        $wp_tests_options = $this->originalOptions;

        // Clear config cache
        Fuerte_Wp_Config::invalidate_cache();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function test_complete_flow_config_to_filter_execution()
    {
        // Arrange: Set up complete configuration
        global $wp_tests_options;
        $wp_tests_options['_fuertewp_status'] = 'enabled';
        $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = 'hello-dolly/hello.php';
        $wp_tests_options['_fuertewp_blocked_plugins|||1|value'] = 'akismet/akismet.php';
        $wp_tests_options['_fuertewp_blocked_themes|||0|value'] = 'twentytwentythree';

        // Act: Load configuration
        $config = Fuerte_Wp_Config::get_config(true);

        // Assert: Config should contain blocked items
        $this->assertIsArray($config['blocked_plugins'], 'Config should have blocked plugins array');
        $this->assertIsArray($config['blocked_themes'], 'Config should have blocked themes array');
        $this->assertCount(2, $config['blocked_plugins'], 'Should have 2 blocked plugins');
        $this->assertCount(1, $config['blocked_themes'], 'Should have 1 blocked theme');

        // Act: Initialize auto-update manager and test filters
        $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();

        // Create mock update transients
        $pluginTransient = new stdClass();
        $pluginTransient->response = [
            'hello-dolly/hello.php' => (object)['slug' => 'hello-dolly', 'new_version' => '1.6.0'],
            'akismet/akismet.php' => (object)['slug' => 'akismet', 'new_version' => '5.0.0'],
            'jetpack/jetpack.php' => (object)['slug' => 'jetpack', 'new_version' => '10.0.0'],
        ];
        $pluginTransient->no_update = [];
        $pluginTransient->checked = [];

        $themeTransient = new stdClass();
        $themeTransient->response = [
            'twentytwentythree' => (object)['slug' => 'twentytwentythree', 'new_version' => '1.5.0'],
            'twentytwentyfour' => (object)['slug' => 'twentytwentyfour', 'new_version' => '1.2.0'],
        ];
        $themeTransient->no_update = [];
        $themeTransient->checked = [];

        // Act: Apply block filters
        $pluginResult = $manager->block_plugin_updates($pluginTransient);
        $themeResult = $manager->block_theme_updates($themeTransient);

        // Assert: Blocked items should be removed from transients
        $this->assertFalse(
            isset($pluginResult->response['hello-dolly/hello.php']),
            'Blocked plugin should be removed from plugin update transient'
        );
        $this->assertFalse(
            isset($pluginResult->response['akismet/akismet.php']),
            'Blocked plugin should be removed from plugin update transient'
        );
        $this->assertTrue(
            isset($pluginResult->response['jetpack/jetpack.php']),
            'Non-blocked plugin should remain in plugin update transient'
        );

        $this->assertFalse(
            isset($themeResult->response['twentytwentythree']),
            'Blocked theme should be removed from theme update transient'
        );
        $this->assertTrue(
            isset($themeResult->response['twentytwentyfour']),
            'Non-blocked theme should remain in theme update transient'
        );
    }

    /**
     * @test
     */
    public function test_handles_empty_blocked_lists_in_integration()
    {
        // Arrange: Set up configuration with no blocked items
        global $wp_tests_options;
        $wp_tests_options['_fuertewp_status'] = 'enabled';
        // No blocked plugins or themes set

        // Act: Load configuration
        $config = Fuerte_Wp_Config::get_config(true);

        // Assert: Should return empty arrays
        $this->assertIsArray($config['blocked_plugins'], 'Should return array for blocked plugins');
        $this->assertIsArray($config['blocked_themes'], 'Should return array for blocked themes');
        $this->assertEmpty($config['blocked_plugins'], 'Should have no blocked plugins');
        $this->assertEmpty($config['blocked_themes'], 'Should have no blocked themes');

        // Act: Test filters with empty blocked lists
        $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();

        $pluginTransient = new stdClass();
        $pluginTransient->response = [
            'hello-dolly/hello.php' => (object)['slug' => 'hello-dolly', 'new_version' => '1.6.0'],
        ];

        $themeTransient = new stdClass();
        $themeTransient->response = [
            'twentytwentythree' => (object)['slug' => 'twentytwentythree', 'new_version' => '1.5.0'],
        ];

        $pluginResult = $manager->block_plugin_updates($pluginTransient);
        $themeResult = $manager->block_theme_updates($themeTransient);

        // Assert: All items should remain when blocked lists are empty
        $this->assertTrue(
            isset($pluginResult->response['hello-dolly/hello.php']),
            'Plugin should remain when blocked list is empty'
        );
        $this->assertTrue(
            isset($themeResult->response['twentytwentythree']),
            'Theme should remain when blocked list is empty'
        );
    }

    /**
     * @test
     */
    public function test_preserves_blocked_config_through_cache_invalidation()
    {
        // Arrange: Set up initial configuration
        global $wp_tests_options;
        $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = 'hello-dolly/hello.php';
        $wp_tests_options['_fuertewp_blocked_themes|||0|value'] = 'twentytwentythree';

        // Act: Load config, invalidate, and reload
        $config1 = Fuerte_Wp_Config::get_config();
        Fuerte_Wp_Config::invalidate_cache();
        $config2 = Fuerte_Wp_Config::get_config();

        // Assert: Both configs should match
        $this->assertSame($config1['blocked_plugins'], $config2['blocked_plugins'], 'Blocked plugins should persist through cache invalidation');
        $this->assertSame($config1['blocked_themes'], $config2['blocked_themes'], 'Blocked themes should persist through cache invalidation');
    }

    /**
     * @test
     */
    public function test_blocks_and_defers_can_coexist()
    {
        // Arrange: Set up configuration with both blocked and deferred items
        global $wp_tests_options;
        $wp_tests_options['_fuertewp_status'] = 'enabled';
        $wp_tests_options['_fuertewp_blocked_plugins|||0|value'] = 'hello-dolly/hello.php';
        $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'akismet/akismet.php';

        // Act: Load configuration
        $config = Fuerte_Wp_Config::get_config(true);

        // Assert: Both arrays should be populated independently
        $this->assertCount(1, $config['blocked_plugins'], 'Should have 1 blocked plugin');
        $this->assertCount(1, $config['deferred_plugins'], 'Should have 1 deferred plugin');

        // Act: Initialize manager and test both filters work together
        $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();

        // Test that blocked item is removed from transient
        $transient = new stdClass();
        $transient->response = [
            'hello-dolly/hello.php' => (object)['slug' => 'hello-dolly', 'new_version' => '1.6.0'],
            'akismet/akismet.php' => (object)['slug' => 'akismet', 'new_version' => '5.0.0'],
        ];
        $transient->no_update = [];
        $transient->checked = [];

        $result = $manager->block_plugin_updates($transient);

        // Assert: Blocked plugin should be removed
        $this->assertFalse(isset($result->response['hello-dolly/hello.php']));

        // Test that deferred plugin is excluded from auto-update
        $deferredPlugin = new stdClass();
        $deferredPlugin->plugin = 'akismet/akismet.php';

        $autoUpdateResult = $manager->exclude_deferred_plugins(true, $deferredPlugin);

        // Assert: Deferred plugin should be excluded from auto-update
        $this->assertFalse($autoUpdateResult, 'Deferred plugin should be excluded from auto-update');
    }
}
