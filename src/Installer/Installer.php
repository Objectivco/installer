<?php

namespace StellarWP\Installer;

class Installer {
	/**
	 * @var ?string
	 */
	protected static $hook_prefix;

	/**
	 * Is this libary initialized?
	 *
	 * @since 1.0.0
	 *
	 * @var Installer|null
	 */
	public static $instance;

	/**
	 * Registered plugins.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $plugins = [];

	/**
	 * Service provider.
	 *
	 * @since 1.0.0
	 *
	 * @var Provider
	 */
	private $provider;

	/**
	 * Registered themes.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $themes = [];

	/**
	 * Initializes the service provider.
	 *
	 * @since 1.0.0
	 *
	 * @return Installer
	 */
	public static function init(): Installer {
		if ( static::$instance ) {
			return static::$instance;
		}

		static::$instance = new self();

		return static::$instance;
	}

	/**
	 * Helper function for ::init().
	 *
	 * @since 1.0.0
	 *
	 * @return Installer
	 */
	public static function get(): Installer {
		return static::init();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->provider = new Provider();
		$this->provider->register();
	}

	/**
	 * Deregisters a plugin for installation / activation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 *
	 * @return bool Whether the plugin was deregistered or not.
	 */
	public function deregister_plugin( string $plugin_slug ): bool {
		if ( ! isset( $this->plugins[ $plugin_slug ] ) ) {
			return false;
		}

		$hook_prefix = Config::get_hook_prefix();

		remove_action( "wp_ajax_stellarwp_installer_{$hook_prefix}_install_plugin_{$plugin_slug}", [ $this->plugins[ $plugin_slug ], 'handle_request' ] );

		unset( $this->plugins[ $plugin_slug ] );

		/**
		 * Fires when a plugin is deregistered.
		 *
		 * @since 1.0.0
		 *
		 * @param string $plugin_slug The slug of the plugin.
		 */
		do_action( "stellarwp/installer/{$hook_prefix}/deregister_plugin", $plugin_slug );

		return true;
	}

	/**
	 * Gives the JS object name used for handling JS behaviors.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_js_object(): string {
		$hook_prefix = Config::get_hook_prefix();

		return "stellarwpInstaller{$hook_prefix}";
	}

	/**
	 * Gives the JS selectors indexed by slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_js_selectors(): array {
		$plugins   = $this->get_plugins();

		$selectors = [];

		foreach ( $plugins as $plugin_slug => $plugin ) {
			$selectors[ $plugin_slug ] = $plugin->get_button()->get_selector();
		}

		return $selectors;
	}

	/**
	 * Generates a nonce for the installer.
	 *
	 * @since 1.0.0
	 *
	 * @return false|string
	 */
	public function get_nonce() {
		return wp_create_nonce( $this->get_nonce_name() );
	}

	/**
	 * Gets the nonce name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_nonce_name(): string {
		$hook_prefix = Config::get_hook_prefix();

		/**
		 * Filters the nonce name.
		 *
		 * @since 1.0.0
		 *
		 * @param string $nonce_name The nonce name.
		 */
		return apply_filters( "stellarwp/installer/{$hook_prefix}/nonce_name", 'stellarwp_installer_resource_install' );
	}

	/**
	 * Gets registered plugins.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_plugins(): array {
		return $this->plugins;
	}

	/**
	 * Gets the provider.
	 *
	 * @since 1.0.0
	 *
	 * @return Provider
	 */
	public function get_provider(): Provider {
		return $this->provider;
	}

	/**
	 * Gets registered themes.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_themes(): array {
		return $this->themes;
	}

	/**
	 * Returns whether or not a plugin/theme is active.
	 *
	 * @param string $slug Resource slug.
	 *
	 * @return bool
	 */
	public function is_active( string $slug ): bool {
		if ( isset( $this->plugins[ $slug ] ) ) {
			return $this->plugins[ $slug ]->is_active();
		}

		if ( isset( $this->themes[ $slug ] ) ) {
			return $this->themes[ $slug ]->is_active();
		}

		if ( is_plugin_active( $slug ) ) {
			return true;
		}

		$active_theme = wp_get_theme();

		if ( $active_theme == $slug ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns whether or not a plugin/theme is installed.
	 *
	 * @param string $slug Resource slug.
	 *
	 * @return bool
	 */
	public function is_installed( string $slug ): bool {
		if ( $this->is_plugin_installed( $slug ) ) {
			return true;
		}

		if ( $this->is_theme_installed( $slug ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns whether or not a theme is installed.
	 *
	 * @param string $slug Resource slug.
	 *
	 * @return bool
	 */
	public function is_plugin_installed( string $slug ): bool {
		if ( isset( $this->plugins[ $slug ] ) ) {
			return $this->plugins[ $slug ]->is_installed();
		}

		return false;
	}

	/**
	 * Returns whether or not a theme is installed.
	 *
	 * @param string $slug Resource slug.
	 *
	 * @return bool
	 */
	public function is_theme_installed( string $slug ): bool {
		if ( isset( $this->themes[ $slug ] ) ) {
			return $this->themes[ $slug ]->is_installed();
		}

		$themes = wp_get_themes();
		foreach ( $themes as $theme ) {
			// @phpstan-ignore-next-line
			if ( $theme->Name == $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registers a plugin for installation / activation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 * @param string $plugin_name The name of the plugin.
	 * @param string $plugin_basename The basename of the plugin.
	 * @param string|null $download_url The download URL of the plugin.
	 * @param string|null $did_action The action that indicates that a plugin is active.
	 *
	 * @return void
	 */
	public function register_plugin( string $plugin_slug, string $plugin_name, string $plugin_basename, ?string $download_url = null, ?string $did_action = null ): void {
		// If the plugin is already registered, deregister it first so we don't have duplicate actions.
		if ( isset( $this->plugins[ $plugin_slug ] ) ) {
			$this->deregister_plugin( $plugin_slug );
		}

		$hook_prefix = Config::get_hook_prefix();
		$js_action   = "stellarwp_installer_{$hook_prefix}_install_plugin_{$plugin_slug}";

		$handler     = new Handler\Plugin(
			$plugin_name,
			$plugin_slug,
			$plugin_basename,
			$download_url,
			$did_action,
			$js_action
		);

		$this->plugins[ $plugin_slug ] = $handler;

		add_action( "wp_ajax_{$js_action}", [ $handler, 'handle_request' ] );

		/**
		 * Fires when a plugin is registered.
		 *
		 * @since 1.0.0
		 *
		 * @param string $plugin_slug The slug of the plugin.
		 * @param string $plugin_name The name of the plugin.
		 * @param string $plugin_basename The basename of the plugin.
		 * @param string|null $download_url The download URL of the plugin.
		 */
		do_action( "stellarwp/installer/{$hook_prefix}/register_plugin", $plugin_slug, $plugin_name, $plugin_basename, $download_url );
	}
}
