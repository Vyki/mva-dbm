<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm;

class Helpers
{

	public static function fetchPairs($data, $key = NULL, $value = NULL)
	{
		$return = [];

		if (!is_array($data) && !$data instanceof \Traversable) {
			throw new InvalidArgumentException('Parameter $data must be array or Traversable object!');
		}

		if ($key === NULL && $value === NULL) {
			throw new InvalidArgumentException('FetchPairsHelper requires defined key or value!');
		}

		if ($key === NULL) {
			foreach ($data as $row) {
				$return[] = $row[$value];
			}
		} elseif ($value === NULL) {
			foreach ($data as $row) {
				$return[is_object($row[$key]) ? (string) $row[$key] : $row[$key]] = $row;
			}
		} else {
			foreach ($data as $row) {
				$return[is_object($row[$key]) ? (string) $row[$key] : $row[$key]] = $row[$value];
			}
		}

		return $return;
	}

}
