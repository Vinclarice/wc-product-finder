<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

/**
 * Server-side mirror of rules.js — turns question config + answers into
 * MatchEngine rules, so the no-JS fallback (build order step 8) reuses the
 * exact same matching logic as the JS-driven experience via URL state
 * instead of client state. Pure PHP, no WordPress calls, so it's testable
 * without a WP bootstrap, same as MatchEngine.
 *
 * $answers here always comes from $_GET, not the DOM — a toggle question's
 * "on" state is signaled by its key being present at all (an HTML form
 * omits unchecked checkboxes from its submission entirely), not by a
 * boolean value the way a JS checkbox's `.checked` property works.
 */
final class RuleBuilder {

	public static function build( array $questions, array $answers ): array {
		$rules = array();
		foreach ( $questions as $question ) {
			if ( ! self::should_include( $question, $answers ) ) {
				continue;
			}
			$rules[] = self::build_rule( $question, $answers[ $question['key'] ] ?? null );
		}
		return $rules;
	}

	private static function should_include( array $question, array $answers ): bool {
		if ( 'toggle' === $question['input']['type'] ) {
			return array_key_exists( $question['key'], $answers );
		}

		$value = $answers[ $question['key'] ] ?? null;
		if ( $value === null || $value === '' ) {
			return false;
		}

		// A malformed no-JS $_GET value for a numeric question (e.g. a
		// hand-crafted or bot-submitted URL) is treated as unanswered rather
		// than coerced — casting garbage to 0 would silently produce a
		// near-always-permissive filter instead of just skipping the rule.
		$is_numeric_type = in_array( $question['valueType'], array( 'int', 'float' ), true );
		return ! $is_numeric_type || is_numeric( $value );
	}

	private static function build_rule( array $question, $answer_value ): array {
		$rule = array(
			'attribute'  => $question['attribute'],
			'type'       => $question['ruleType'],
			'comparator' => $question['comparator'],
			'value'      => self::rule_value( $question, $answer_value ),
		);
		if ( 'soft' === $question['ruleType'] ) {
			$rule['weight'] = $question['weight'];
		}
		return $rule;
	}

	/**
	 * Casts based on the question's declared valueType (matching
	 * TentsTemplate::attribute_map()'s type for the same attribute), not the
	 * comparator. This used to branch on comparator (gte/lte -> numeric,
	 * else -> string), which silently broke season_rating: its comparator is
	 * 'equals' but its attribute is int-typed, so the old logic always
	 * produced a string rule value that could never strictly-equal the
	 * product's actual int value. 'equals' specifically needs the exact same
	 * type as the product's cast value (3 === 3.0 is false in both PHP and
	 * JS), so int and float aren't interchangeable "numeric" here the way
	 * they are for gte/lte.
	 */
	private static function rule_value( array $question, $answer_value ) {
		// A toggle's rule value is the fixed threshold from its config (e.g.
		// "under 5 lb"), not the checkbox's own submitted value.
		if ( 'toggle' === $question['input']['type'] ) {
			return $question['input']['value'];
		}

		switch ( $question['valueType'] ) {
			case 'int':
				return (int) $answer_value;
			case 'float':
				return (float) $answer_value;
			default:
				return strtolower( trim( (string) $answer_value ) );
		}
	}
}
