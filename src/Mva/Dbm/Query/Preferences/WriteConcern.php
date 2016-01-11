<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Query\Preferences;

class MongoWriteConcern
{

	const MAJORITY = 'majority';

	/** @var string|int|NULL */
	private $w = 1;

	/** @var int */
	private $wtimeout = 10000;

	/** @var bool|NULL */
	private $journal;

	public function __construct($w = 1, $wtimeout = 10000, $journal = FALSE)
	{
		$this->w = $w;
		$this->wtimeout = $wtimeout;
		$this->journal = (bool) $journal;
	}

	public function getJournal()
	{
		return $this->journal;
	}

	public function getW()
	{
		return $this->w;
	}

	public function getWtimeout()
	{
		return $this->wtimeout;
	}

}
