<?php

/*
@param $array [array]
	- source array, can be multidimensional
	- REQUIRED

@param $keys [string]
	- the key (name) of item in array which will be used to sort array by
	- REQUIRED

@param $limit [integer]
	- limiting value, for more info see below
	- default 5

@params $limitType [integer]
	- type of limit, use together with $limit: 
		1: 	sets max number of items in column (or sub-array), then needed number of columns (sub-arrays) is created
		2: 	sets max number of columns (or sub-arrays) created. Items are then distributted equally to each column (sub-array)
			if possible, else remaining items are added to first columns (sub-arrays), so first n columns (sub-arrays) have n+1 items
		3:	sets max number of columns (or sub-arrays) created. Items are then distributted equally to each column (sub-array)
			if possible, else remaining items are in last column (sub-array)
	- default 1

@params $groupingType [integer]
	- sets type of indexes in associative array containing groupped items:
		1:	do nothing, just numeric indexes
		2:	indexes, which are named using alphabetic range of items contained in group 
	- default 1

@param $captionsType [integer]
	- sets type of alphabetic range to display, more in examples
		1: only one start and end letter describes group (1: A-B, 2: B-C)
		2: multiple letters describes groups if needed to not have same endpoint of first group as startpoint of second (1: A-Ba, 2: Bb-C)
		3: use whole words to describe range, principle as in option 1 - endpoint for first group can be startpoint for second
	-default 1

*/

Class AlphabetGrouper {

	private $limit = 0;
	private $keys = NULL;
	private $limitType = 0;
	private $groupingType = 0;
	private $captionsType = 0;

	private $unsortedArray = NULL;
	private $sortedArray = NULL;
	private $groupedArray = NULL;
	private $groupsArray = NULL;

	private $groupingCalcs = Array();
	private $defaultLimit = 5;

	public function __construct($array=false, $keys=false, $limit=false, $limitType=false, $groupingType=false, $captionsType=false) {
		if ($array !== false) { $this->start($array, $keys, $limit, $limitType, $groupingType, $captionsType); }
	}

	private function start($array=false, $keys=false, $limit=false, $limitType=false, $groupingType=false, $captionsType=false) {
		if (( $array === false )||( $array === NULL )) {
			if ($this->unsortedArray === NULL) {
				throw new InvalidArgumentException('You must give an Array for sorting. Use constructor or pass it to coresponding function');
			} else {
				$this->init($keys, $limit, $limitType, $groupingType, $captionsType);
			}
		} else if (gettype($array) != "array") {
			throw new InvalidArgumentException('You must give an Array for sorting. ' . gettype($array) . ' given instead');
		} else {
			$this->unsortedArray = $array;
			$this->init($keys, $limit, $limitType, $groupingType, $captionsType);
		}
    }

    private function init($keys, $limit, $limitType, $groupingType, $captionsType) {
    	$this->set_limit($limit); 
		$this->set_field_to_order_by($keys); 
		$this->set_limit_type($limitType);
		$this->set_grouping_type($groupingType);
		$this->set_captions_type($captionsType);
    }

	public function set_limit($num) {
		if (gettype($num) == 'integer') {
			if ($num <= 0) {
				throw new InvalidArgumentException('limit parameter ḿust be bigger than zero. ' . $num . ' value given');
			} else {
				$this->limit = $num;
			}
		} else if (( $num === NULL )||( $num === false )) {
			if ($this->limit == 0) {
				$this->limit = $this->defaultLimit;
			}			
		}
	}

	public function set_field_to_order_by($keys) {
		$type = gettype($keys);
		if ($type == 'string' || $type == 'integer') {
			$this->keys = $keys;
		} else {
			if ($keys === false) {
				if ($this->keys === NULL) {
					throw new InvalidArgumentException('array key pointing to the value that array will be sorted by was not set.
													    Use parameter in constructor, this method or use method set_field_to_order_by($key) 
													    to set it.');
				} else {
					/* pouzit prvy prvok vymysliet to */
				}
			}
		}
	}

	public function set_limit_type($type) {
		$this->set_var($type, "limitType", 3);
	}

	public function set_grouping_type($type) {
		$this->set_var($type, "groupingType", 2);
	}

	public function set_captions_type($type) {
		$this->set_var($type, "captionsType", 3);
	}

    public function sort_az($array=false, $keys=false) {
    	$this->start($array, $keys);
    	$arrcpy = $this->unsortedArray;
    	$tmparr = $this->array_column_recursive($arrcpy, $this->keys);
    	array_multisort(array_map('strtolower', $tmparr), $arrcpy, SORT_NATURAL);
    	$this->sortedArray = $arrcpy;
    	return $arrcpy;
    }

    public function sort_za($array=false, $keys=false) {
    	$this->sortedArray = array_reverse($this->sort_az($array, $keys));
    	return $this->sortedArray();
    }

    public function group_az($array=false, $keys=false, $limit=false, $limitType=false, $groupingType=false) {
    	$this->sort_az($array, $keys);
    	$this->grouping();
    	return $this->groupedArray;
    }

    public function caption_groups($toStringArray=false) {
    	if ($toStringsArray === false) {
    		return $this->groupsArray;
    	} else {
    		return $this->groupsArrayToStrings();
    	}
    }

    private function grouping() {
    	$tmparr = Array();
    	$this->groupingMath();

    	if ($this->limitType == 2) {
    		// fixed number of columns (blocks) - using this method, in every column will be equal number of items except last one if possible
    		// if not for example you have 463 items and 200 columns, this method will produce last column with 63 items
    		// in this case limitType method 3 is used
    		if ($this->groupingCalcs['remainder'] > $this->groupingCalcs['items_per_column_no_remainder']) {
    			$tmparr = $this->group(1);
    		} else {
    			$tmparr = $this->group(2);
    		}
    	} else if ($this->limitType == 1) {
    		// fixed numbers of items in column (block) - will create needed amount of columns (blocks)
    		$tmparr = array_chunk($this->sortedArray, $this->limit);
    	} else if ($this->limitType == 3) {
    		// fixed number of columns (blocks) - items are divided into equal columns (blocks) and remaining items (if any) 
    		// then divided into first columns (blocks) 
    		$tmparr = $this->group(1);
    	}

    	$this->generate_captions($tmparr, $this->captionsType);

    	if ($this->groupingType == 2) {
    		// resulting groupped array is associative array with keys names describing range of items in collumn (block)
    		$this->groupedArray = array_combine($this->groupsArrayToStrings() , $tmparr);
    	} else if ($this->groupingType == 1) {
    		// resulting groupped array is associative array with only numeric keys
    		$this->groupedArray = $tmparr;
    	}
    }

    private function generate_captions($array, $type=1, $match=NULL, $i=0) {
    	if ($match === NULL) {
    		$this->groupsArray = Array();
    	}
       	$groupsCount = sizeof($array);

       	if ($i < $groupsCount) {
    		if ($type == 1) {
    			// simplest method - just get first letter from first and last item in column.
    			// example: A-B, B-D, F-G
				$this->groupsArray[$i][0] = strtoupper($array[$i] [0] [$this->keys] [0]);
				$this->groupsArray[$i][1] = strtoupper($array[$i] [(sizeof($array[$i])-1)] [$this->keys] [0]);
				$this->generate_captions($array, $type, TRUE, ++$i);
			} else if ($type == 2) {
				// more progressive method - if last item in first columns starts with same letter as first item in second column, 
				// adds extra letter(s) to captions until there is diffference. 
				// example: A-Ba, Bb-D, F-G
    			$nextChar = ($match !== NULL) ? strlen($match) : 0;
    			$current_first = strtoupper($array[$i] [0] [$this->keys] [$nextChar]);
    			$previous_last = strtoupper($array[($i-1)] [(sizeof(($array[$i-1]))-1)] [$this->keys] [$nextChar]);

    			if ($current_first == $previous_last) {
    				$match .= $current_first;
    				$this->generate_captions($array, $type, $match, $i);
    			} else {
		    		if ($i == 0) {
						$this->groupsArray[0][0] = strtoupper($array[0] [0] [$this->keys] [0]);
		    		} else {
	    				$this->groupsArray[$i][0] = strtoupper( substr($array[$i] [0] [$this->keys], 0, $nextChar+1) );
						$this->groupsArray[$i-1][1] = strtoupper( substr($array[($i-1)] [(sizeof($array[$i-1])-1)] [$this->keys], 0, $nextChar+1) );
		    		}
		    		if ($i == $groupsCount-1) {
		    			$this->groupsArray[$i][1] = strtoupper($array[$i] [(sizeof($array[$i])-1)] [$this->keys] [0]);
		    		}
					$this->generate_captions($array, $type, "", ++$i);
    			}
			} else if ($type == 3) {
				// same principe as method 1, only uses whole word in captions
				$this->groupsArray[$i][0] = preg_split('/\s+/', $array[$i] [0] [$this->keys])[0];
				$this->groupsArray[$i][1] = preg_split('/\s+/', $array[$i] [(sizeof($array[$i])-1)] [$this->keys])[0];
				$this->generate_captions($array, $type, TRUE, ++$i);
			} else if ($type == 4) {
				// same principe as method 2, add words until difference
				/* maybe in future, not needed now */
			}

    	}

    }

    private function group($type) {
    	// fuill up the groups array with sorted items
    	$k=0;
    	$arr = Array();
    	$itms = 0;
		for ($i=0; $i<$this->limit; $i++) {
			if ($type == 1) {
				$itms = ($i < $this->groupingCalcs['remainder']) ? $this->groupingCalcs['items_per_column_no_remainder'] + 1 : $this->groupingCalcs['items_per_column_no_remainder'];
			} else if ($type == 2) {
				$itms = $this->groupingCalcs['items_per_column'];
			}
			for ($j=0; $j<$itms; $j++) {
				if ($k <$this->groupingCalcs['items']) {
					$arr[$i][$j] = $this->sortedArray[$k];
					$k++;
				} else {
					break;
				}
			}
		}
		return $arr;
    }

    private function groupingMath() {
    	// some calculations 
    	$this->groupingCalcs['items'] = sizeof($this->sortedArray);
		if ($this->groupingCalcs['items'] % $this->limit == 0) {
			$this->groupingCalcs['items_per_column'] = $this->groupingCalcs['items'] / $this->limit;
			$this->groupingCalcs['items_per_column_no_remainder'] = $this->groupingCalcs['items_per_column'];
			$this->groupingCalcs['remainder'] = 0;
		} else {
			$this->groupingCalcs['remainder'] = $this->groupingCalcs['items'] % $this->limit;
			$this->groupingCalcs['items_per_column_no_remainder'] = ($this->groupingCalcs['items'] - $this->groupingCalcs['remainder']) / $this->limit;
			$this->groupingCalcs['items_per_column'] = (($this->groupingCalcs['items'] - $this->groupingCalcs['remainder']) / $this->limit) + 1;
		}
    }

    private function array_column_recursive($input=NULL, $columnKey=NULL, $indexKey=NULL) {
    	// recursive implementation of PHP's array_column() function
    	// http://php.net/manual/en/function.array-column.php
    	// inspiration stolen from here: https://github.com/tripflex/wp-login-flow/blob/master/functions.php
		if ( ! is_array( $input ) ) { return NULL; }
		
		$resultArray = array();
		foreach ( $input as $row ) {
			$key    = $value = NULL;
			$keySet = $valueSet = FALSE;
			if ( $indexKey !== NULL && array_key_exists( $indexKey, $row ) ) {
				$keySet = TRUE;
				$key    = (string) $row[ $indexKey ];
			}
			if ( $columnKey === NULL ) {
				$valueSet = TRUE;
				$value    = $row;
			} elseif ( is_array( $row ) && array_key_exists( $columnKey, $row ) ) {
				$valueSet = TRUE;
				$value    = $row[ $columnKey ];
			}

			$possibleValue = $this->array_column_recursive( $row, $columnKey, $indexKey );
			if ( $possibleValue ) {
				$resultArray = array_merge( $possibleValue, $resultArray );
			}

			if ( $valueSet ) {
				if ( $keySet ) {
					$resultArray[ $key ] = $value;
				} else {
					$resultArray[ ] = $value;
				}
			}
		}
		return $resultArray;
    }

    private function groupsArrayToStrings() {
    	// groupsArray is aasociative array correspongding to columns (groups) containing two elements for each.
    	// first contains start letter (range) and second last
    	// this converts these two into string that looks like "A - B"
    	$fx = function ($x) {
			return implode(" - ", $x);
		};
		return array_map($fx, $this->groupsArray);
    }

    private function set_var($val, $var, $maxRange) {
    	// function for sanitizing input from set methods, trying to do DRY practice
		if (( $val === NULL )||( $val === false )) {
			if ($this->{$var} === 0) { $this->{$var} = 1; }
		} else if (!is_int($val)) {
			throw new InvalidArgumentException($var . ' parameter can be only integer. ' . gettype($val) . ' given instead');
		} else if ((is_int($val))&&(($val > $maxRange)&&($val < 1))) {
			throw new InvalidArgumentException('invalid value given for ' . $var . '. groupingType can be only integer in range from 1 to ' . $maxRange);
		} else if (is_int($val)) {
			$this->{$var} = $val;
		}
	}
}

?>