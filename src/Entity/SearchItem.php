<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Baraja\Search\Helpers;

class SearchItem
{

	/**
	 * @var int
	 */
	private $score = 0;

	/**
	 * @var string
	 */
	private $query;

	/**
	 * @var string|null
	 */
	private $title;

	/**
	 * @var string
	 */
	private $snippet;

	/**
	 * @var object
	 */
	private $entity;

	/**
	 * @param object $entity
	 * @param string $query
	 * @param string|null $title
	 * @param string $snippet
	 * @param int|null $score
	 */
	public function __construct($entity, string $query, ?string $title, string $snippet, ?int $score = null)
	{
		$this->entity = $entity;
		$this->query = $query;
		$this->title = trim($title);
		$this->snippet = trim($snippet);

		if ($score !== null) {
			$this->setScore($score);
		}
	}

	/**
	 * @return object
	 */
	public function getEntity()
	{
		return $this->entity;
	}

	/**
	 * @return string|null
	 */
	public function getTitle(): ?string
	{
		if ($this->title === null) {
			return null;
		}

		return (string) preg_replace('/\`(\S+)\`/', '$1', $this->title);
	}

	/**
	 * @return string
	 */
	public function getSnippet(): string
	{
		return $this->snippet;
	}

	/**
	 * @return string|null
	 */
	public function getTitleHighlighted(): ?string
	{
		if ($this->getTitle() === null) {
			return null;
		}

		return Helpers::highlightFoundWords($this->getTitle(), $this->query);
	}

	/**
	 * @param int $length
	 * @param bool $normalize
	 * @return string
	 */
	public function getSnippetHighlighted(int $length = 160, bool $normalize = false): string
	{
		if ($this->getSnippet() === '') {
			return '';
		}

		return Helpers::highlightFoundWords(
			htmlspecialchars(
				htmlspecialchars_decode(htmlspecialchars(
					Helpers::smartTruncate(
						$this->query,
						($normalize ? $this->normalize($this->snippet ? : '') : $this->snippet),
						$length
					), ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES)
			),
			$this->query
		);
	}

	/**
	 * @return string[]
	 */
	public function entityToArray(): array
	{
		try {
			$properties = (new \ReflectionClass($this))->getProperties();
		} catch (\ReflectionException $e) {
			return [];
		}

		$return = [];

		foreach ($properties as $property) {
			$return[$property->name] = Helpers::normalize((string) $this->{$property->name});
		}

		return $return;
	}

	/**
	 * @return int
	 */
	public function getScore(): int
	{
		return $this->score;
	}

	/**
	 * @param int $score
	 * @param int $min
	 * @param int $max
	 */
	public function setScore(int $score, int $min = 0, int $max = 512): void
	{
		if ($score > $max) {
			$score = $max;
		}

		if ($score < $min) {
			$score = $min;
		}

		$this->score = $score;
	}

	/**
	 * @param string $haystack
	 * @return string
	 */
	private function normalize(string $haystack): string
	{
		$haystack = strip_tags($haystack);
		$haystack = (string) str_replace("\n", ' ', $haystack);
		$haystack = (string) preg_replace('/(--+|==+|\*\*+)/', '', $haystack);
		$haystack = (string) preg_replace('/\s+\|\s+/', ' ', $haystack);
		$haystack = (string) preg_replace('/```(\w+\n)?/', '', $haystack);
		$haystack = (string) preg_replace('/\`(\S+)\`/', '$1', $haystack);
		$haystack = (string) preg_replace('/\s*(\-\s+){2,}\s*/', ' - ', $haystack);
		$haystack = (string) preg_replace('/\s+/', ' ', $haystack);

		return $haystack;
	}

}