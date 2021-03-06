<?php

declare(strict_types=1);

namespace Baraja\Search\ScoreCalculator;


interface IScoreCalculator
{
	/**
	 * $mode means user modificators for specific preference settings.
	 */
	public function process(string $haystack, string $query, string $mode = null): int;
}
