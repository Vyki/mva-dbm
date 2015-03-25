<?php

namespace Mva\Mongo;

/**
 * Representation of single document obtained by the aggregation function
 *
 * @author     Roman Vykuka
 */
class AggregatedDocument extends Document
{

	public function update($data)
	{
		throw new NotSupportedException('Update operation is not allowed in aggregated result');
	}

	public function delete()
	{
		throw new NotSupportedException('Delete operation is not allowed in aggregated result');
	}

	public function &__get($key)
	{
		if (!array_key_exists($key, $this->data) && isset($this->data['_id'][$key])) {
			return $this->data['_id'][$key];
		}

		return parent::__get($key);
	}

}
