<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm\Collection\Document;

class DocumentFactory implements IDocumentFactory
{

	/** @return Document */
	public function create($data)
	{
		return new Document($data);
	}

}
