<?php

/**
 * Copyright 2017 Nick Korbel
 *
 * This file is part of Booked Scheduler.
 *
 * Booked Scheduler is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Booked Scheduler is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Booked Scheduler.  If not, see <http://www.gnu.org/licenses/>.
 */
class ResourceImportCsvRow
{
	public $name;
	public $status;
	public $schedule;
	public $resourceType;
	public $sortOrder = 0;
	public $location;
	public $contact;
	public $description;
	public $notes;
	public $resourceAdministrator;
	public $color;
	public $resourceGroups = array();
	public $autoAssign = true;
	public $approvalRequired = false;
	public $capacity;
	public $attributes = array();

	private $values = array();
	private $indexes = array();

	/**
	 * @param $values array
	 * @param $indexes array
	 * @param $attributes CustomAttribute[]
	 */
	public function __construct($values, $indexes, $attributes)
	{
		$this->values = $values;
		$this->indexes = $indexes;

		$this->name = $this->valueOrDefault('name');
		$this->status = strtolower($this->valueOrDefault('status'));
		$this->schedule = strtolower($this->valueOrDefault('schedule'));
		$this->resourceType = strtolower($this->valueOrDefault('resourceType'));
		$this->sortOrder = $this->valueOrDefault('sortOrder');
		$this->location = $this->valueOrDefault('location');
		$this->contact = $this->valueOrDefault('contact');
		$this->description = $this->valueOrDefault('description');
		$this->notes = $this->valueOrDefault('notes');
		$this->resourceAdministrator = strtolower($this->valueOrDefault('resourceAdministrator'));
		$this->color = $this->valueOrDefault('color');
		$this->resourceGroups = (!array_key_exists('resourceGroups', $this->indexes) || $indexes['resourceGroups'] === false) ? array()
				: array_map('trim', explode(',', htmlspecialchars($values[$indexes['resourceGroups']])));
		$this->autoAssign = strtolower($this->valueOrDefault('autoAssign'));
		$this->approvalRequired = strtolower($this->valueOrDefault('approvalRequired'));
		$this->capacity = $this->valueOrDefault('capacity');
		foreach ($attributes as $label => $attribute)
		{
			$this->attributes[$label] = $this->valueOrDefault($label);
		}

	}

	public function IsValid()
	{
		$isValid = !empty($this->name);
		if (!$isValid)
		{
			Log::Debug('Resource import row is not valid. Missing name');
		}
		return $isValid;
	}

	/**
	 * @param string[] $values
	 * @param CustomAttribute[] $attributes
	 * @return bool|string[]
	 */
	public static function GetHeaders($values, $attributes)
	{
		if (!in_array('name', $values) && !in_array('name', $values))
		{
			return false;
		}

		$indexes['name'] = self::indexOrFalse('name', $values);
		$indexes['status'] = self::indexOrFalse('status', $values);
		$indexes['schedule'] = self::indexOrFalse('schedule', $values);
		$indexes['resourceType'] = self::indexOrFalse('resource type', $values);
		$indexes['sortOrder'] = self::indexOrFalse('sort order', $values);
		$indexes['location'] = self::indexOrFalse('location', $values);
		$indexes['contact'] = self::indexOrFalse('contact', $values);
		$indexes['description'] = self::indexOrFalse('description', $values);
		$indexes['notes'] = self::indexOrFalse('notes', $values);
		$indexes['resourceAdministrator'] = self::indexOrFalse('resource administrator', $values);
		$indexes['color'] = self::indexOrFalse('color', $values);
		$indexes['resourceGroups'] = self::indexOrFalse('resource groups', $values);
		$indexes['autoAssign'] = self::indexOrFalse('auto assign permissions', $values);
		$indexes['approvalRequired'] = self::indexOrFalse('approval required', $values);
		$indexes['capacity'] = self::indexOrFalse('capacity', $values);

		foreach ($attributes as $label => $attribute)
		{
			$escapedLabel = str_replace('\'', '\\\\', $label);
			$indexes[$label] = self::indexOrFalse($escapedLabel, $values);
		}

		return $indexes;
	}

	private static function indexOrFalse($columnName, $values)
	{
		$values = array_map('strtolower', $values);
		$index = array_search($columnName, $values);
		if ($index === false)
		{
			return false;
		}

		return intval($index);
	}

	/**
	 * @param $column string
	 * @return string
	 */
	private function valueOrDefault($column)
	{
		return ($this->indexes[$column] === false ||
				!array_key_exists($this->indexes[$column], $this->values)) ? ''
				: htmlspecialchars(trim($this->values[$this->indexes[$column]]));
	}
}

class ResourceImportCsv
{
	/**
	 * @var UploadedFile
	 */
	private $file;

	/**
	 * @var int[]
	 */
	private $skippedRowNumbers = array();

	/**
	 * @var CustomAttribute[]
	 */
	private $attributes;

	/**
	 * @param UploadedFile $file
	 * @param CustomAttribute[] $attributes
	 */
	public function __construct(UploadedFile $file, $attributes)
	{
		$this->file = $file;
		$this->attributes = $attributes;
	}

	/**
	 * @return ResourceImportCsvRow[]
	 */
	public function GetRows()
	{
		$rows = array();

		$contents = $this->file->Contents();

		$contents = $this->RemoveUTF8BOM($contents);
		$csvRows = preg_split('/\n|\r\n?/', $contents);

		if (count($csvRows) == 0)
		{
			Log::Debug('No rows in resource import file');
			return $rows;
		}

		Log::Debug('%s rows in resource import file', count($csvRows));

		$headers = ResourceImportCsvRow::GetHeaders(str_getcsv($csvRows[0]), $this->attributes);

		if (!$headers)
		{
			Log::Debug('No headers in user import file');
			return $rows;
		}

		for ($i = 1; $i < count($csvRows); $i++)
		{
			$values = str_getcsv($csvRows[$i]);

			$row = new ResourceImportCsvRow($values, $headers, $this->attributes);

			if ($row->IsValid())
			{
				$rows[] = $row;
			}
			else
			{
				Log::Error('Skipped import of resource row %s. Values %s', $i, print_r($values, true));
				$this->skippedRowNumbers[] = $i;
			}
		}

		return $rows;
	}

	/**
	 * @return int[]
	 */
	public function GetSkippedRowNumbers()
	{
		return $this->skippedRowNumbers;
	}

	private function RemoveUTF8BOM($text)
	{
		return str_replace("\xEF\xBB\xBF", '', $text);
	}
}