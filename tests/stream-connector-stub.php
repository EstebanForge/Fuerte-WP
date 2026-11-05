<?php
/**
 * Stream connector base-class stub (tests only).
 *
 * Mirrors the public contract of WP_Stream\Connector
 * (xwp/stream classes/class-connector.php) closely enough for the
 * fuerte-wp connector to extend it and for the dispatcher to resolve
 * callback_{action} from the current filter.
 *
 * @package fuerte-wp
 */

namespace WP_Stream;

// phpcs:ignoreFile
if ( class_exists( 'WP_Stream\Connector' ) ) {
	return;
}

abstract class Connector {
	public $name = '';
	public $actions = array();
	public $register_frontend = true;
	protected $is_registered = false;

	abstract public function get_label();

	abstract public function get_context_labels();

	abstract public function get_action_labels();

	public function is_dependency_satisfied() {
		return true;
	}

	public function register() {
		if ( $this->is_registered ) {
			return;
		}
		foreach ( $this->actions as $action ) {
			\add_action( $action, array( $this, 'callback' ), 10, 99 );
		}
		$this->is_registered = true;
	}

	public function unregister() {
		$this->is_registered = false;
	}

	public function callback() {
		$action   = \current_filter();
		$callback = array( $this, 'callback_' . preg_replace( '/[^a-z0-9_]/', '_', $action ) );
		if ( is_callable( $callback ) ) {
			return call_user_func_array( $callback, func_get_args() );
		}
	}

	public function log( $message, $args, $object_id, $context, $action, $user_id = null ) {
		return true;
	}

	public function get_changed_keys( $old_value, $new_value, $deep = false ) {
		$old     = is_array( $old_value ) ? $old_value : array();
		$new     = is_array( $new_value ) ? $new_value : array();
		$changed = array();
		foreach ( $new as $key => $value ) {
			if ( ! array_key_exists( $key, $old ) || $old[ $key ] !== $value ) {
				$changed[] = $key;
			}
		}
		foreach ( $old as $key => $value ) {
			if ( ! array_key_exists( $key, $new ) ) {
				$changed[] = $key;
			}
		}
		return $changed;
	}
}
