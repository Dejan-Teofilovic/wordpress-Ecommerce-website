<?php
namespace AIOSEO\Plugin\Common\Traits\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains array specific helper methods.
 *
 * @since 4.1.4
 */
trait Arrays {
	/**
	 * Unsets a given value in a given array.
	 * This should only be used if the given value only appears once in the array.
	 *
	 * @since 4.0.0
	 *
	 * @param  array  $array The array.
	 * @param  string $value The value that needs to be removed from the array.
	 * @return array  $array The filtered array.
	 */
	public function unsetValue( $array, $value ) {
		if ( in_array( $value, $array, true ) ) {
			unset( $array[ array_search( $value, $array, true ) ] );
		}

		return $array;
	}

	/**
	 * Compares two multidimensional arrays to see if they're different.
	 *
	 * @since 4.0.0
	 *
	 * @param  array   $array1 The first array.
	 * @param  array   $array2 The second array.
	 * @return boolean         Whether the arrays are different.
	 */
	public function arraysDifferent( $array1, $array2 ) {
		foreach ( $array1 as $key => $value ) {
			// Check for non-existing values.
			if ( ! isset( $array2[ $key ] ) ) {
				return true;
			}
			if ( is_array( $value ) ) {
				if ( $this->arraysDifferent( $value, $array2[ $key ] ) ) {
					return true;
				}
			} else {
				if ( $value !== $array2[ $key ] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks whether the given array is associative.
	 * Arrays that only have consecutive, sequential numeric keys are numeric.
	 * Otherwise they are associative.
	 *
	 * @since 4.1.4
	 *
	 * @param  array $array The array.
	 * @return bool         Whether the array is associative.
	 */
	public function isArrayAssociative( $array ) {
		return 0 < count( array_filter( array_keys( $array ), 'is_string' ) );
	}

	/**
	 * Checks whether the given array is numeric.
	 *
	 * @since 4.1.4
	 *
	 * @param  array $array The array.
	 * @return bool         Whether the array is numeric.
	 */
	public function isArrayNumeric( $array ) {
		return ! $this->isArrayAssociative( $array );
	}

	/**
	 * Recursively replaces the values from one array with the ones from another.
	 * This function should act identical to the built-in array_replace_recursive(), with the exception that it also replaces array values with empty arrays.
	 *
	 * @since 4.2.4
	 *
	 * @param  array $targetArray      The target array
	 * @param  array $replacementArray The array with values to replace in the target array.
	 * @return array                   The modified array.
	 */
	public function arrayReplaceRecursive( $targetArray, $replacementArray ) {
		// In some cases the target array isn't an array yet (due to e.g. race conditions in InternalOptions), so in that case we can just return the replacement array.
		if ( ! is_array( $targetArray ) ) {
			return $replacementArray;
		}

		foreach ( $replacementArray as $k => $v ) {
			// If the key does not exist yet on the target array, add it.
			if ( ! isset( $targetArray[ $k ] ) ) {
				$targetArray[ $k ] = $replacementArray[ $k ];
				continue;
			}

			// If the value is an array, only try to recursively replace it if the value isn't empty.
			// Otherwise empty arrays will be ignored and won't override the existing value of the target array.
			if ( is_array( $v ) && ! empty( $v ) ) {
				$targetArray[ $k ] = $this->arrayReplaceRecursive( $targetArray[ $k ], $v );
				continue;
			}

			// Replace with non-array value or empty array.
			$targetArray[ $k ] = $v;
		}

		return $targetArray;
	}

	/**
	 * Recursively intersects the two given arrays.
	 * You can pass in an optional argument (allowedKey) to restrict the intersect to arrays with a specific key.
	 * This is needed when we are e.g. sanitizing array values before setting/saving them to an option.
	 *
	 * @since 4.2.5
	 *
	 * @param  array  $array1     The first array.
	 * @param  array  $array2     The second array.
	 * @param  string $allowedKey The only key the method should run for (optional).
	 * @param  string $parentKey  The parent key.
	 * @return array              The intersected array.
	 */
	public function arrayIntersectRecursive( $array1, $array2, $allowedKey = '', $parentKey = '' ) {
		if ( ! $allowedKey || $allowedKey === $parentKey ) {
			$array1 = array_intersect_assoc( $array1, $array2 );
		}

		if ( empty( $array1 ) ) {
			return [];
		}

		foreach ( $array1 as $k => $v ) {
			if ( is_array( $v ) && isset( $array2[ $k ] ) ) {
				$array1[ $k ] = $this->arrayIntersectRecursive( $array1[ $k ], $array2[ $k ], $allowedKey, $k );
			}
		}

		if ( $this->isArrayNumeric( $array1 ) ) {
			$array1 = array_values( $array1 );
		}

		return $array1;
	}
}