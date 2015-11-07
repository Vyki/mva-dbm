<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm;

class Helpers
{

	public static function expandArray(array $data, $delimiter = '.')
	{
		$return = [];

		foreach ($data as $key => $val) {
			self::expandRow($return, $key, $val);
		}

		return $return;
	}

	public static function expandRow(&$data, $key, $val, $delimiter = '.')
	{
		$parts = explode($delimiter, $key);
		$leafPart = array_pop($parts);
		$parent = &$data;

		foreach ($parts as $part) {
			if (!isset($parent[$part]) || !is_array($parent[$part])) {
				$parent[$part] = [];
			}
			$parent = &$parent[$part];
		}

		if (empty($parent[$leafPart])) {
			$parent[$leafPart] = $val;
		}
	}

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
