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
		return $value !== null && $value !== '';
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

	private static function rule_value( array $question, $answer_value ) {
		// A toggle's rule value is the fixed threshold from its config (e.g.
		// "under 5 lb"), not the checkbox's own submitted value.
		if ( 'toggle' === $question['input']['type'] ) {
			return $question['input']['value'];
		}
		if ( in_array( $question['comparator'], array( 'gte', 'lte' ), true ) ) {
			return (float) $answer_value;
		}
		return strtolower( trim( (string) $answer_value ) );
	}
}
