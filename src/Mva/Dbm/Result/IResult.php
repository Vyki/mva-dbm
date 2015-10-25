<?php

namespace Mva\Dbm\Result;

interface IResult extends \Traversable
{

	public function fetch();

	public function fetchAll();

	public function fetchPairs($key = NULL, $value = NULL);

	public function getResult();

	public function normalizeDocument($document);
}
