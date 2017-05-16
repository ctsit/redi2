<?php
/**
 * Created by PhpStorm.
 * User: kbergqui
 * Date: 11/6/13
 * Time: 4:06 PM
 */

class FieldSorter {
	public $field;

	function __construct($field) {
		$this->field = $field;
	}

	function cmp($a, $b) {
		if ($a[$this->field] == $b[$this->field]) return 0;
		return ($a[$this->field] > $b[$this->field]) ? 1 : -1;
	}
} 