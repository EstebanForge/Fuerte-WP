<?php

use PHPUnit\Framework\TestCase;

/**
 * Deferred Updates Integration Tests
 *
 * @since 1.8.0
 * Tests the complete flow from config to filter execution
 */
class DeferredUpdatesIntegrationTest extends TestCase
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
    public function it_complete_flow_config_to_filter_execution()
    {
        // Arrange: Set up complete configuration
        global $wp_tests_options;
        $wp_tests_options['_fuertewp_status'] = 'enabled';
        $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'hello-dolly/hello.php';
        $wp_tests_options['_fuertewp_deferred_plugins|||1|value'] = 'akismet/akismet.php';
        $wp_tests_options['_fuertewp_deferred_themes|||0|value'] = 'twentytwentythree';

        // Act: Load configuration
        $config = Fuerte_Wp_Config::get_config(true);

        // Assert: Config should contain deferred items
        $this->assertIsArray($config['deferred_plugins'], 'Config should have deferred plugins array');
        $this->assertIsArray($config['deferred_themes'], 'Config should have deferred themes array');
        $this->assertCount(2, $config['deferred_plugins'], 'Should have 2 deferred plugins');
        $this->assertCount(1, $config['deferred_themes'], 'Should have 1 deferred theme');

        // Act: Initialize auto-update manager and test filters
        $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();

        // Create test items
        $deferredPlugin = new stdClass();
        $deferredPlugin->plugin = 'hello-dolly/hello.php';

        $allowedPlugin = new stdClass();
        $allowedPlugin->plugin = 'some-other-plugin/some-other-plugin.php';

        $deferredTheme = new stdClass();
        $deferredTheme->theme = 'twentytwentythree';

        $allowedTheme = new stdClass();
        $allowedTheme->theme = 'twentytwentyfour';

        // Assert: Deferred items should be excluded
        $this->assertFalse(
            $manager->exclude_deferred_plugins(true, $deferredPlugin),
            'Deferred plugin should be excluded from auto-update'
        );

        $this->assertTrue(
            $manager->exclude_deferred_plugins(true, $allowedPlugin),
            'Non-deferred plugin should be allowed to auto-update'
        );

        $this->assertFalse(
            $manager->exclude_deferred_themes(true, $deferredTheme),
            'Deferred theme should be excluded from auto-update'
        );

        $this->assertTrue(
            $manager->exclude_deferred_themes(true, $allowedTheme),
            'Non-deferred theme should be allowed to auto-update'
        );
    }

    /**
     * @test
     */
    public function it_handles_empty_deferred_lists_in_integration()
    {
        // Arrange: Set up configuration with no deferred items
        global $wp_tests_options;
        $wp_tests_options['_fuertewp_status'] = 'enabled';
        // No deferred plugins or themes set

        // Act: Load configuration
        $config = Fuerte_Wp_Config::get_config(true);

        // Assert: Should return empty arrays
        $this->assertIsArray($config['deferred_plugins'], 'Should return array for deferred plugins');
        $this->assertIsArray($config['deferred_themes'], 'Should return array for deferred themes');
        $this->assertEmpty($config['deferred_plugins'], 'Should have no deferred plugins');
        $this->assertEmpty($config['deferred_themes'], 'Should have no deferred themes');

        // Act: Test filters with empty deferred lists
        $manager = Fuerte_Wp_Auto_Update_Manager::get_instance();

        $testPlugin = new stdClass();
        $testPlugin->plugin = 'hello-dolly/hello.php';

        $testTheme = new stdClass();
        $testTheme->theme = 'twentytwentythree';

        // Assert: All items should be allowed when deferred lists are empty
        $this->assertTrue(
            $manager->exclude_deferred_plugins(true, $testPlugin),
            'Plugin should be allowed when deferred list is empty'
        );

        $this->assertTrue(
            $manager->exclude_deferred_themes(true, $testTheme),
            'Theme should be allowed when deferred list is empty'
        );
    }

    /**
     * @test
     */
    public function it_preserves_deferred_config_through_cache_invalidation()
    {
        // Arrange: Set up initial configuration
        global $wp_tests_options;
        $wp_tests_options['_fuertewp_deferred_plugins|||0|value'] = 'hello-dolly/hello.php';
        $wp_tests_options['_fuertewp_deferred_themes|||0|value'] = 'twentytwentythree';

        // Act: Load config, invalidate, and reload
        $config1 = Fuerte_Wp_Config::get_config();
        Fuerte_Wp_Config::invalidate_cache();
        $config2 = Fuerte_Wp_Config::get_config();

        // Assert: Both configs should match
        $this->assertSame($config1['deferred_plugins'], $config2['deferred_plugins'], 'Deferred plugins should persist through cache invalidation');
        $this->assertSame($config1['deferred_themes'], $config2['deferred_themes'], 'Deferred themes should persist through cache invalidation');
    }
}
