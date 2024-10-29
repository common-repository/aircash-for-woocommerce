<?php


use Endroid\QrCode\Encoding\EncodingInterface;

final class Aircash_Encoding implements EncodingInterface
{
	/** @var string */
	private $value;

	public function __construct(string $value)
	{
		$this->value = $value;
	}

	public function __toString(): string
	{
		return $this->value;
	}
}
