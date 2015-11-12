<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm;

class Helpers
{

	public static function contractArray(array $data, $delimiter = '.', $list = FALSE)
	{
		$return = [];

		if (is_bool($delimiter)) {
			list($list, $delimiter) = [$delimiter, '.'];
		}

		$getkey = function ($data, $prefix) use (&$getkey, &$return, $delimiter, $list) {
			foreach ($data as $key => $row) {
				if (!is_array($row) || empty($row) || (is_int(key($row)) && !$list)) {
					$return[$prefix . $key] = $row;
				} else {
					$getkey($row, $prefix . $key . $delimiter);
				}
			}
		};

		$getkey($data, '');

		return $return;
	}

	public static function expandArray(array $data, $delimiter = '.')
	{
		$return = [];

		foreach ($data as $key => $val) {
			self::expandRow($return, $key, $val, $delimiter);
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
