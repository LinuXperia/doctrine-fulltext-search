<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *    name="search__search_query",
 *    indexes={
 *       @Index(name="search__search_query__query_id", columns={"query", "id"}),
 *       @Index(name="search__search_query__results", columns={"results"}),
 *       @Index(name="search__search_query__frequency", columns={"frequency"})
 *    }
 * )
 */
class SearchQuery
{
	use UuidIdentifier;

	/**
	 * @var string
	 * @ORM\Column(type="string", unique=true)
	 */
	private $query;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private $frequency = 1;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private $results;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private $score;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $insertedDate;

	/**
	 * @var \DateTime|null
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $updatedDate;


	public function __construct(string $query, int $results, int $score = 0)
	{
		$this->query = trim($query);
		$this->results = $results < 0 ? 0 : $results;
		$this->setScore($score);
		try {
			$this->insertedDate = new \DateTime('now');
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
	}


	public function getQuery(): string
	{
		return $this->query;
	}


	public function getFrequency(): int
	{
		return $this->frequency;
	}


	public function addFrequency(int $frequency = 1): self
	{
		$this->frequency += $frequency;

		return $this;
	}


	public function getResults(): int
	{
		return $this->results;
	}


	public function setResults(int $results): self
	{
		$this->results = $results;

		return $this;
	}


	public function getScore(): int
	{
		return $this->score;
	}


	public function setScore(int $score): self
	{
		if ($score < 0) {
			$this->score = 0;
		} elseif ($score > 100) {
			$this->score = 100;
		} else {
			$this->score = $score;
		}

		return $this;
	}


	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): ?\DateTime
	{
		return $this->updatedDate;
	}


	public function setUpdatedDate(\DateTime $updatedDate): self
	{
		$this->updatedDate = $updatedDate;

		return $this;
	}


	public function setUpdatedNow(): self
	{
		try {
			$this->updatedDate = new \DateTime('now');
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}

		return $this;
	}
}
