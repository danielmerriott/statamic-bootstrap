<?php
require_once('vendor/Cake/Utility/Inflector.php');

/**
 * Comb
 * A simple (yet highly-customizable) search layer that helps users find
 * needles in data haystacks based on relevancy.
 *
 * Copyright (c) 2013, FredHQ
 * All Rights Reserved.
 */
class Comb {

	// query methods
	// ----------------------------------------------------------------------

	const QUERY_BOOLEAN = 0;
	const QUERY_WORDS   = 1;
	const QUERY_WHOLE   = 2;


	// matching & scoring
	// ----------------------------------------------------------------------

	/**
	 * Match weights
	 * @var array
	 */
	private $match_weights = array(
		"partial_word" => 1,
		"partial_first_word" => 2,
		"partial_word_start" => 1,
		"partial_first_word_start" => 2,
		"whole_word" => 5,
		"whole_first_word" => 5,
		"partial_whole" => 2,
		"partial_whole_start" => 2,
		"whole" => 10
	);

	/**
	 * Minimum characters to search over
	 * @var int
	 */
	private $min_characters = 3;

	/**
	 * Minimum characters per word to include word in search
	 * @var int
	 */
	private $min_word_characters = 2;

	/**
	 * Score threshold
	 * @var int
	 */
	private $score_threshold = 1;

	/**
	 * Property weights
	 * @var array
	 */
	private $property_weights = array();


	// input
	// ----------------------------------------------------------------------

	/**
	 * Query mode
	 * @var int
	 */
	private $query_mode = self::QUERY_BOOLEAN;

	/**
	 * Should query be stemmed?
	 * @var boolean
	 */
	private $use_stemming = false;

	/**
	 * Should query use alternate character values?
	 * @var boolean
	 */
	private $use_alternates = false;

	/**
	 * Should the full query be included in boolean searches?
	 * @var boolean
	 */
	private $include_full_query = true;

	/**
	 * A list of words filtered out of search queries
	 * @var array
	 */
	private $stop_words = array();


	// output
	// ----------------------------------------------------------------------

	/**
	 * Limit the number of results returned
	 * @var int
	 */
	private $limit = null;

	/**
	 * Should we throw the too-many-results exception?
	 * @var boolean
	 */
	private $enable_too_many_results = false;

	/**
	 * Should we sort results by score?
	 * @var boolean
	 */
	private $sort_by_score = true;

	/**
	 * Should we group results by category?
	 * @var boolean
	 */
	private $group_by_category = false;


	// data
	// ----------------------------------------------------------------------

	/**
	 * Haystack of data to look through
	 * @var array
	 */
	private $haystack = array();

	/**
	 * A list of properties to exclude
	 * @var array
	 */
	private $exclude_properties = array();

	/**
	 * A list of the only properties to include
	 * @var array
	 */
	private $include_properties = array();


	// internal data trackers
	// ----------------------------------------------------------------------

	/**
	 * Has the data already been prepared?
	 * @var boolean
	 */
	private $is_data_prepared = false;

	/**
	 * Is the haystack categorized?
	 * @var boolean
	 */
	private $is_haystack_categorized = false;

	/**
	 * The start time of a query (for measuring performance)
	 * @var int
	 */
	private $query_start_time = false;

	/**
	 * The end time of a query (for measuring performance)
	 * @var int
	 */
	private $query_end_time = false;



	// start up
	// ----------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @param mixed  $data_target  JSON of data or URL to find data
	 * @param array  $settings  An array of settings for overriding defaults
	 * @return Comb
	 */
	public function __construct($data_target, $settings=array())
	{
		$this->setHaystack($data_target);
		$this->setSettings($settings);
	}


	/**
	 * Set the current haystack to deal with
	 *
	 * @param string  $data  Data to parse for haystack
	 * @return void
	 * @throws CombBadDataException
	 */
	private function setHaystack($data)
	{
		// if this is not an array, try to resolve it
		if (!is_array($data)) {
			// is this not JSON? it might be a remote file
			if (!$this->isJSON($data)) {
				$data = file_get_contents($data);
			}

			// attempt to convert to PHP
			$data = json_decode($data);
		}

		// make sure that we found JSON
		if (!$data) {
			throw new CombBadDataException('Could not create usable data out of the `$data` given.');
		}

		reset($data);
		$first = each($data);
		reset($data);

		if (!is_numeric($first['key'])) {
			$this->is_haystack_categorized = true;
		}

		// this is JSON, use this as the data
		$this->haystack = $data;
	}


	/**
	 * Look up a $query in the $haystack
	 *
	 * @param string  $query  Query to look up
	 * @return array
	 */
	public function lookUp($query)
	{
		// start query
		$this->markStartQueryTime();

		// preformat
		$query = $this->preformat($query);

		// test for validity
		$this->testValidQuery($query);

		// determine search parameters
		$params = $this->parseSearchParameters($query);

		// prepare data
		$this->prepareData($params);

		// trim haystack to fit query
		$this->haystack = $this->removeDisallowedMatches($params);

		// search over data
		return $this->searchOverData($params, $query);
	}


	/**
	 * Set settings
	 *
	 * @param array  $settings  Array of options
	 * @return void
	 */
	public function setSettings($settings)
	{
		if (!is_array($settings)) {
			return;
		}

		// match weights
		if (isset($settings['match_weights']) && !is_null($settings['match_weights']) && is_array($settings['match_weights'])) {
			$this->match_weights = array_merge($this->match_weights, $settings['match_weights']);
		}

		// min characters
		if (isset($settings['min_characters']) && !is_null($settings['min_characters'])) {
			$this->min_characters = $settings['min_characters'];
		}

		// min word characters
		if (isset($settings['min_word_characters']) && !is_null($settings['min_word_characters'])) {
			$this->min_word_characters = $settings['min_word_characters'];
		}

		// score threshold
		if (isset($settings['score_threshold']) && !is_null($settings['score_threshold'])) {
			$this->score_threshold = $settings['score_threshold'];
		}

		// property weights
		if (isset($settings['property_weights']) && !is_null($settings['property_weights']) && is_array($settings['property_weights'])) {
			$this->property_weights = array_merge($this->property_weights, $settings['property_weights']);
		}

		// query mode
		if (isset($settings['query_mode']) && !is_null($settings['query_mode'])) {
			switch (strtolower($settings['query_mode'])) {
				case 'boolean':
					$this->query_mode = self::QUERY_BOOLEAN;
					break;

				case 'words':
					$this->query_mode = self::QUERY_WORDS;
					break;

				case 'whole':
					$this->query_mode = self::QUERY_WHOLE;
					break;
			}
		}

		// include full query
		if (isset($settings['include_full_query']) && !is_null($settings['include_full_query']) && in_array($settings['include_full_query'], array(false, 'false', 'no', 0, '0'))) {
			$this->include_full_query = false;
		}

        // stop words
        if (isset($settings['stop_words']) && !is_null($settings['stop_words']) && is_array($settings['stop_words'])) {
            $this->stop_words = array_merge($this->stop_words, $settings['stop_words']);
        }

		// limit
		if (isset($settings['limit']) && !is_null($settings['limit'])) {
			$this->limit = (int) $settings['limit'];
		}

		// sort by score
		if (isset($settings['sort_by_score']) && !is_null($settings['sort_by_score']) && in_array($settings['sort_by_score'], array(false, 'false', 'no', 0, '0'))) {
			$this->sort_by_score = false;
		}

		// use stemming
		if (isset($settings['use_stemming']) && !is_null($settings['use_stemming']) && in_array($settings['use_stemming'], array(true, 'true', 'yes', 1, '1'))) {
			$this->use_stemming = true;
		}

		// use alternates
		if (isset($settings['use_alternates']) && !is_null($settings['use_alternates']) && in_array($settings['use_alternates'], array(true, 'true', 'yes', 1, '1'))) {
			$this->use_alternates = true;
		}

		// sort by score
		if (isset($settings['sort_by_score']) && !is_null($settings['sort_by_score']) && in_array($settings['sort_by_score'], array(false, 'false', 'no', 0, '0'))) {
			$this->sort_by_score = false;
		}

		// group by category
		if (isset($settings['group_by_category']) && !is_null($settings['group_by_category']) && in_array($settings['group_by_category'], array(true, 'true', 'yes', 1, '1'))) {
			$this->group_by_category = true;
		}

		// exclude properties
		if (isset($settings['exclude_properties']) && !is_null($settings['exclude_properties']) && is_array($settings['exclude_properties'])) {
			$this->exclude_properties = array_merge($this->exclude_properties, $settings['exclude_properties']);
		}

		// include properties
		if (isset($settings['include_properties']) && !is_null($settings['include_properties']) && is_array($settings['include_properties'])) {
			$this->include_properties = array_merge($this->include_properties, $settings['include_properties']);
		}
	}


	// formatting & parsing
	// ----------------------------------------------------------------------

	/**
	 * Preformats a query for searching
	 *
	 * @param string  $raw_query  The raw query to format
	 * @return string
	 */
	private function preformat($raw_query)
	{
		return trim(preg_replace("/[^\w\d\-\+\s&’'‘]/i", "", $raw_query));
	}


	/**
	 * Prepares data for querying
	 *
	 * @return void
	 */
	private function prepareData()
	{
		$output = array();

		if ($this->is_data_prepared) {
			return;
		}

		// find non-categorized data
		if (!$this->is_haystack_categorized) {
			foreach ($this->haystack as $item) {
				$record = (array) $item;
				$record['_category'] = 'data';
				array_push($output, $record);
			}

		// find categorized data
		} else {
			foreach ($this->haystack as $category => $records) {
				foreach ($records as $item) {
					$record = (array) $item;
					$record['_category'] = $category;
					array_push($output, $record);
				}
			}
		}

		// remove any disallowed properties in the settings
		$output = $this->removeDisallowedProperties($output);

		// store data as our haystack and mark as prepared
		$this->haystack = $output;
		$this->is_data_prepared = true;
	}


	/**
	 * Multidimensional array flattener
	 *
	 * @param mixed  $item  Item to flatten
	 * @param string  $glue  Optional glue to stick between items
	 * @return string
	 */
	private function flattenArray($item, $glue=" ") {
		$output = "";

		if (!is_array($item)) {
			return preg_replace('#\s+#ism', ' ', $item);
		}

		foreach ($item as $part) {
			$output .= (is_array($part)) ? $this->flattenArray($part, $glue) : $glue . $part;
		}
		
		return preg_replace('#\s+#ism', ' ', $output);
	}


	/**
	 * Look through each result attempting to find good matches
	 *
	 * @param array  $params  Parameters for search
	 * @param string  $raw_query  Raw query to search for
	 * @return array
	 * @throws CombException
	 * @throws CombNoResultsFoundException
	 */
	private function searchOverData($params, $raw_query)
	{
		// make sure there's data to search over
		if (!count($this->haystack)) {
			// the haystack is empty because it was parsed by a boolean
			// search return that no results were found
			if ($params['required']) {
				throw new CombNoResultsFoundException("No results found.");

			// otherwise, the haystack is empty and that's an error
			} else {
				throw new CombException("Empty haystack.");
			}
		}

		// set up informational object to be returned alongside data object
		$info = array(
			'total_results' => 0,
			'raw_query' => $raw_query,
			'parsed_query' => $params,
			'query_time' => 0
		);

		// loop over records
		foreach ($this->haystack as $key => $item) {
			$data = $item['pruned'];

			// counters
			$found = array(
				"partial_word" => 0,
				"partial_first_word" => 0,
				"partial_word_start" => 0,
				"partial_first_word_start" => 0,
				"whole_word" => 0,
				"whole_first_word" => 0,
				"partial_whole" => 0,
				"partial_whole_start" => 0,
				"whole" => 0
			);

			// loop over each query chunk
			foreach ($params['chunks'] as $chunk) {
				$escaped_chunk = str_replace('#', '\#', $chunk);
				$regex = array(
					"whole" => '#^' . $escaped_chunk . '$#i',
					"partial" => '#' . $escaped_chunk . '#i',
					"partial_from_start" => '#^' . $escaped_chunk . '#i'
				);

				// loop over each data property
				foreach ($data as $name => $property) {
					$property = $this->flattenArray($property);

					if (!is_string($property)) {
						continue;
					}

					$words = preg_split("#\s#i", $property);
					$strength = (!isset($this->property_weights[$name])) ? 1 : $this->property_weights[$name];

					// reset iterator
					$i = 0;

					// whole matching
					$result = preg_match_all($regex['whole'], $property, $matches);
					if ($result) {
						$found['whole'] += $strength * $result;
					}

					$result = preg_match_all($regex['partial'], $property, $matches);
					if ($result) {
						$found['partial_whole'] += $strength * $result;
					}

					$result = preg_match_all($regex['partial_from_start'], $property, $matches);
					if ($result) {
						$found['partial_whole_start'] += $strength * $result;
					}

					// word matching
					foreach ($words as $word) {
						$result = preg_match_all($regex['whole'], $word, $matches);
						if ($result) {
							$found['whole_word'] += $strength * $result;

							if ($i === 0) {
								$found['whole_first_word'] += $strength * $result;
							}
						}

						$result = preg_match_all($regex['partial'], $word, $matches);
						if ($result) {
							$found['partial_word'] += $strength * $result;

							if ($i === 0) {
								$found['partial_first_word'] += $strength * $result;
							}
						}

						$result = preg_match_all($regex['partial_from_start'], $word, $matches);
						if ($result) {
							$found['partial_word_start'] += $strength * $result;

							if ($i === 0) {
								$found['partial_first_word_start'] += $strength * $result;
							}
						}

						$i++;
					}
				}

				// calculate score
				$score = 0;

				// loop through match weights, taking user-set options if we can
				foreach ($this->match_weights as $weight_type => $weight) {
					$score += $found[$weight_type] * $weight;
				}

				$this->haystack[$key]['score'] = $score;
			}
		}

		// create a clone
		$clone = $this->haystack;

		// perform sorting
		if ($this->sort_by_score) {
			usort($clone, function($a, $b) {
				if ($a['score'] > $b['score']) {
					return -1;
				} elseif ($a['score'] < $b['score']) {
					return 1;
				} else {
					return 0;
				}
			});
		}

		// create output
		$output = array();

		// only record whose score meets the threshold
		foreach ($clone as $record) {
			if ($record['score'] >= $this->score_threshold) {
				// remove our working object
				unset($record['pruned']);

				// add to output
				array_push($output, $record);
			}
		}

		// add total results to info array
		$info['total_results'] = count($output);
		$output_length = 0;

		// if grouping by category, rearrange - will handle limiting
		if ($this->group_by_category && $this->is_haystack_categorized) {
			$categorized_output = array();

			foreach ($output as $item) {
				if (!isset($categorized_output[$item['category']])) {
					$categorized_output[$item['category']] = array();
					$output_length++;
				}

				if (is_null($this->limit) || ($this->limit && count($categorized_output[$item['category']]) < $this->limit)) {
					array_push($categorized_output[$item['category']], $item);
				}
			}

			$output = $categorized_output;

		// or trim outputs to limit if it was set	
		} elseif ($this->limit) {
			// if we do not want more results than the limit
			if ($this->enable_too_many_results && count($output) > $this->limit) {
				throw new CombTooManyResultsException('Too many results found.');
			}

			$output = array_slice($output, 0, $this->limit);
			$output_length = count($output);

		// otherwise, the size is the size
		} else {
			$output_length = count($output);
		}

		// add query time to info array
		$info['query_time'] = $this->markEndQueryTime();

		// if nothing was found
		if ($output_length === 0) {
			throw new CombNoResultsFoundException('No results found.');
		}

		// results were found
		return array('data' => $output, 'info' => $info);
	}


	/**
	 * Removes matches that have been disallowed by a boolean search
	 *
	 * @param array  $params  Parameters for search
	 * @return array
	 */
	private function removeDisallowedMatches($params)
	{
		$disallowed = '#' . join('|', $params['disallowed']) . '#i';
		$required   = '#(?=.*' . join(')(?=.*', $params['required']) . ')#i';
		$new_data   = array();

		// this only applies to boolean mode
		if ($this->query_mode !== self::QUERY_BOOLEAN || (count($params['disallowed']) === 0 && count($params['required']) === 0)) {
			return $this->haystack;
		}

		// loop through data
		foreach ($this->haystack as $item) {
			try {
				$record = "";

				// string pruned results together
				foreach ($item['pruned'] as $pruned) {
					$record .= " " . $pruned;
				}

				// check for disallowed
				if (count($params['disallowed']) && preg_match($disallowed, $record)) {
					// a disallowed was found, we don't want this
					throw new Exception("");
				}

				// check for disallowed
				if (count($params['required']) && !preg_match($required, $record)) {
					// a disallowed was found, we don't want this
					throw new Exception("");
				}

				array_push($new_data, $item);
			} catch (Exception $e) {
				continue;
			}
		}

		return $new_data;
	}


	/**
	 * Removes properties that are on the exclusion list
	 *
	 * @param array  $data  Data to examine
	 * @return array
	 */
	private function removeDisallowedProperties($data)
	{
		$output = array();
		$exclude = (is_array($this->exclude_properties) && count($this->exclude_properties));
		$include = (is_array($this->include_properties) && count($this->include_properties));

		foreach ($data as $item) {
			// get a local copy of item
			$local_item = $item;

			// get and remove category
			$category = $local_item['_category'];
			unset($local_item['_category']);

			// exclude properties
			if ($exclude) {
				foreach ($this->exclude_properties as $excluded) {
					if (isset($local_item[$excluded])) {
						unset($local_item[$excluded]);
					}
				}
			}
			
			// include properties
			if ($include) {
				foreach ($local_item as $key => $value) {
					if (!in_array($key, $this->include_properties)) {
						unset($local_item[$key]);
					}
				}
			}

			array_push($output, array(
				"data" => $item,
				"pruned" => $local_item,
				"score" => 0,
				"category" => $category
			));
		}

		return $output;
	}


	/**
	 * Removes duplicate values in a given array
	 *
	 * @param array  $array  Array to make unique
	 * @return array
	 */
	private function standardizeArray($array)
	{
		// make words lowercase
		$array = array_map('strtolower', $array);

		// sort items (mainly so plural forms come after singular forms)
		sort($array);

		// make array unique
		$array = array_unique($array);

		return $array;
	}


	// validation
	// ----------------------------------------------------------------------

	/**
	 * Tests for a valid query
	 *
	 * @param string  $query  Query to test
	 * @return boolean
	 * @throws CombNoQueryException
	 * @throws CombNotEnoughCharactersException
	 */
	private function testValidQuery($query)
	{
		$length = strlen($query);

		if ($length === 0) {
			throw new CombNoQueryException("No query given.");
		}

		if ($length < $this->min_characters) {
			throw new CombNotEnoughCharactersException("Not enough characters entered.");
		}
	}



	// helpers
	// ----------------------------------------------------------------------

	/**
	 * Is a given string JSON?
	 *
	 * @param string  $string  String to check
	 * @return boolean
	 */
	private function isJSON($string)
	{
		$first_character = substr(trim($string), 0, 1);
		return ($first_character === "{" || $first_character === "[");
	}


	/**
	 * Parses the query for search parameters
	 *
	 * @param string  $query  Query to parse
	 * @return array
	 */
	private function parseSearchParameters($query)
	{
		// set up the array of parts to be returned
		$parts = array(
			"chunks" => array(),
			"required" => array(),
			"disallowed" => array()
		);

		// look for each word
		if ($this->query_mode === self::QUERY_WORDS) {
			$words = preg_split("/\s+/i", $query);

			if ($this->use_alternates) {
				$parts['chunks'] = array_merge($words, $this->getAlternateWords($parts['chunks']));
			}
			
			if ($this->use_stemming) {
				$parts['chunks'] = array_merge($parts['chunks'], $this->getStemmedWords($parts['chunks']));
			} else {
				$paths["chunks"] = $words;
			}

			// add the full query if needed
			if ($this->include_full_query) {
				array_push($parts['chunks'], $query);
			}

		// perform a boolean search -- require words, disallow words
		} elseif ($this->query_mode === self::QUERY_BOOLEAN) {
			$words = preg_split("/\s+/i", $query);

			if ($this->use_alternates) {
				$parts['chunks'] = array_merge($parts['chunks'], $this->getAlternateWords($words));
			}

			foreach ($words as $word) {
				// found a disallowed word (a work prepended with a "-")
				if (strpos($word, '-') === 0 && strlen($word) >= $this->min_word_characters + 1) {
					array_push($parts['disallowed'], substr($word, 1));
				} elseif (strpos($word, '+') === 0 && strlen($word) >= $this->min_word_characters +1) {
					array_push($parts['required'], substr($word, 1));
				} elseif (strlen($word) >= $this->min_word_characters) {
					array_push($parts['chunks'], $word);
				}
			}
			
			if ($this->use_stemming) {
				$parts['chunks'] = array_merge($parts['chunks'], $this->getStemmedWords($parts['chunks']));
			}

			// if all words were required, that's ok -- add them all as chunks
			if (count($parts['required']) > 0 && count($parts['chunks']) === 0) {
				$parts['chunks'] = $parts['required'];
			}

			// add the full query if needed
			if ($this->include_full_query) {
				array_push($parts['chunks'], $query);
			}

		// search for the entire query as one thing
		} else {
			$words = strtolower($query);

			$parts['chunks'] = $words;
		}

		return array(
			'chunks' => $this->standardizeArray($this->filterStopWords($parts['chunks'])),
			'required' => $this->standardizeArray($this->filterStopWords($parts['required'])),
			'disallowed' => $this->standardizeArray($this->filterStopWords($parts['disallowed']))
		);
	}


	/**
	 * Grabs stemmed words
	 * 
	 * @param array  $words  Words to look up
	 * @return array
	 */
	private function getStemmedWords($words)
	{
		$output = array();
		
		if (!is_array($words)) {
			$output = Inflector::singularize($words);
		} else {
			foreach ($words as $word) {
				array_push($output, Inflector::singularize($word));
			}
		}
		
		return $output;
	}


	/**
	 * Attempts to find an alternate word, returns array of alternates or false if none
	 *
	 * @param array  $words  Words to look up
	 * @return mixed
	 */
	private function getAlternateWords($words)
	{
		$output = array();
		
		foreach ($words as $word) {
			if (strtolower($word) == "and") {
				array_push($output, '&');
				continue;
			}
			
			if ($word == "&") {
				array_push($output, 'and');
				continue;
			}
			
			if (strpos($word, "'") !== false) {
				array_push($output, preg_replace("/'/", "‘", $word));
				array_push($output, preg_replace("/'/", "’", $word));
				continue;
			}
			
			if (strpos($word, "’") !== false) {
				array_push($output, preg_replace("/’/", "‘", $word));
				array_push($output, preg_replace("/’/", "'", $word));
				continue;
			}
			
			if (strpos($word, "‘") !== false) {
				array_push($output, preg_replace("/‘/", "'", $word));
				array_push($output, preg_replace("/‘/", "’", $word));
				continue;
			}
		}
		
		return $output;
	}


	/**
	 * Filters out stop words
	 *
	 * @param array $words A list of words to filter
	 * @return array
	 */
	private function filterStopWords($words)
	{
		// short circuit if no stop words are set
		if (!is_array($this->stop_words) || !count($this->stop_words)) {
			return $words;
		}

		foreach ($words as $key => $word) {
			if (in_array($word, $this->stop_words)) {
				unset($words[$key]);
			}
		}

		return $words;
	}


	/**
	 * Mark the start of a query
	 *
	 * @return void
	 */
	private function markStartQueryTime()
	{
		$this->query_start_time = microtime(true);
	}


	/**
	 * Mark the start of a query
	 *
	 * @return int
	 */
	private function markEndQueryTime()
	{
		$this->query_end_time = microtime(true);

		return $this->getQueryTime();
	}


	/**
	 * Get query time
	 *
	 * @return int
	 */
	private function getQueryTime()
	{
		return $this->query_end_time - $this->query_start_time;
	}
	


	// creators for Bloodhound
	// ----------------------------------------------------------------------
	
	/**
	 * Create a new Comb object but remove limit from config
	 *
	 * @param array  $data  Data to search through
	 * @param array  $config  Config array to use (and remove `limit` from)
	 * @return Comb 
	 */
	public static function create($data, $config)
	{
		// limiting is handled on the other end
		if (isset($config['limit'])) {
			unset($config['limit']);
		}
		
		return new Comb($data, $config);
	}
}


/**
 * CombException
 * Basic Comb exception from which others extend
 */
class CombException extends Exception
{

}


/**
 * CombNoResultsFoundException
 * Thrown when no results are found
 */
class CombNoResultsFoundException extends CombException
{

}
/**
 * CombNotEnoughCharactersException
 * Thrown when not enough characters have been entered
 */
class CombNotEnoughCharactersException extends CombException
{

}

/**
 * CombNoQueryException
 * Thrown when no query has been asked
 */
class CombNoQueryException extends CombException
{

}

/**
 * CombBadDataException
 * Thrown when Comb could not successfully load haystack data
 */
class CombBadDataException extends CombException
{

}

/**
 * CombTooManyResultsException
 * Thrown when there are more results than the limit
 */
class CombTooManyResultsException extends CombException
{

}