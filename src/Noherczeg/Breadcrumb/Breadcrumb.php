<?php namespace Noherczeg\Breadcrumb;

/**
 * Breadcrumb
 *
 * Breadcrumb handler package.
 *
 * Check https://github.com/noherczeg/breadcrumb for usage examples!
 *
 * @package     Breadcrumb
 * @version     2.0.4
 * @author      Norbert Csaba Herczeg
 * @license     MIT
 * @copyright   (c) 2013, Norbert Csaba Herczeg
 */

use InvalidArgumentException;
use OutOfRangeException;

class FileNotFoundException extends \Exception
{
}

class Breadcrumb
{

    /** @var String */
    private $base_url = null;

    /** @var Segment[] */
    private $segments = array();

    /** @var Translator */
    private $translator = null;

    /** @var Config */
    private $config = array();

    // you have to expand this if you create your own builders!
    private $build_formats = null;

    /** @var \Noherczeg\Breadcrumb\Builders\Builder */
    private $builder_instance = null;

    public function __construct($base_url = null, $config = 'en')
    {

        // Set defaults
        $base_url = is_null($base_url) ? './' : $base_url;

        // Set objet properties
        $this->base_url = $this->setParam($base_url);

        // Load configurations
        $this->setConfiguration($config);

        // load builders
        $this->build_formats = $this->loadBuilders();
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function loadBuilders()
    {
        $list = array();
        $builderDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'Builders';
        $excluded = array('Builder.php', '.', '..');

        if (!is_dir($builderDirectory))
            throw new \Exception('Can\'t open builder directory, maybe it doesn\'t exists?');

        $handle = opendir($builderDirectory);

        if (!$handle)
            throw new \Exception('Can\'t open builder directory, check the permissions!');

        while (($entry = readdir($handle)) !== false) {
            if (!in_array($entry, $excluded))
                $list[] = strtolower(substr($entry, 0, -11));
        }

        return $list;
    }

    /**
     * Sets the base URL for the package.
     *
     * @param String $urlString Base URL
     * @return \Noherczeg\Breadcrumb\Breadcrumb
     * @throws InvalidArgumentException
     */
    public function setBaseURL($urlString)
    {
        if (!is_string($urlString) && !is_null($urlString)) {
            throw new InvalidArgumentException("Please provide a string as parameter!");
        } else {
            $this->base_url = $urlString;
        }

        return $this;
    }

    /**
     * Dynamic Package configuration
     *
     * @param mixed $config Configurations (either lang code as String, or configuration array)
     * @return \Noherczeg\Breadcrumb\Breadcrumb
     * @throws InvalidArgumentException
     */
    public function setConfiguration($config)
    {
        // Load Util Classes / backwards compatibility
        if (is_string($config)) {
            $this->config = new Config (array('language' => $config));
            $this->translator = new Translator($this->config);
        } else if (is_array($config)) {
            $this->translator = new Translator($config);
        } else {
            throw new InvalidArgumentException("Please provide a string or an array as parameter!");
        }

        return $this;
    }

    /**
     * setParam: basic system method, don't bother.
     *
     * @param mixed $to_this String or null
     * @return String
     * @throws InvalidArgumentException
     */
    private function setParam($to_this)
    {
        if (!is_string($to_this) && !is_null($to_this)) {
            throw new InvalidArgumentException("Please provide a string as parameter!");
        } else {
            return $to_this;
        }
    }

    /**
     * append: Appends an element to the list of Segments. Can do it from both
     * sides, and can mark an element as base element, which means that it'll
     * point to the base URL.
     *
     * Supports method chaining.
     *
     * Warning! It doesn't fix multiple "base element" issues, so it's up to the
     * programmer to append base elements wisely!
     *
     * @param String $raw_name Name of the appendable Segment
     * @param String $side Which side to place the segment in the array
     * @param boolean $base true if it is referring to the base url
     * @param mixed $translate Set to true if you want to use the provided dictionary,
     *                              set to false if you want to skip translation, or
     *                              set to a specific string to assign that value
     * @param bool $disabled
     * @throws \InvalidArgumentException
     * @return \Noherczeg\Breadcrumb\Breadcrumb
     */
    public function append($raw_name = null, $side = 'right', $base = false, $translate = true, $disabled = false)
    {
        $this->checkAppendArgs($raw_name, $side);
        $segment = new Segment($raw_name, $base, $disabled);

        // if translation is set
        if ($translate) {
            if (is_string($translate) && strlen($translate) > 0) {
                // we can set(override) the value manually
                $segment->setTranslated($translate);
            } elseif (is_bool($translate)) {
                // or use the translator service to do it from a selected Dictionary
                $segment->setTranslated($this->translator->translate($raw_name));
            }
        } else {
            $segment->setTranslated($raw_name);
        }

        $this->appendToSide($segment, $side);

        return $this;
    }

    /**
     * @param $raw_name
     * @param $side
     * @throws \InvalidArgumentException
     */
    private function checkAppendArgs($raw_name, $side)
    {
        if (!is_string($raw_name) && !is_int($raw_name) && !in_array($side, array('left', 'right'))) {
            throw new InvalidArgumentException("Wrong type of arguments provided!");
        }
    }

    /**
     * @param $segment Segment
     * @param $side string
     */
    private function appendToSide($segment, $side)
    {
        if ($side === 'left') {
            // Append to the left side
            array_unshift($this->segments, $segment);
        } else {
            // Append to the right side
            $this->segments[] = $segment;
        }
    }

    /**
     * Disables a Segment at the given position.
     *
     * Segment will still remain, but won't be translated, and will be handled
     * specially in the building process.
     *
     * Supports method chaining.
     *
     * @param int $pos Position of the element
     * @return \Noherczeg\Breadcrumb\Breadcrumb
     * @throws OutOfRangeException
     */
    public function disable($pos = null)
    {
        if ($pos === null || !in_array($pos, array_keys($this->segments))) {
            throw new OutOfRangeException('Refering to non existent Segment position!');
        } else {
            $selectedSegment = $this->segments[$pos];
            $selectedSegment->disable();
        }

        return $this;
    }

    /**
     * remove: Removes an element from the list, optionally can reindex the list
     * after removal.
     *
     * Supports method chaining.
     *
     * @param int $pos Position of the element
     * @param boolean $reindex_after_remove To do the reindex or not
     * @return \Noherczeg\Breadcrumb\Breadcrumb
     * @throws OutOfRangeException
     */
    public function remove($pos = 0, $reindex_after_remove = false)
    {
        if (in_array($pos, array_keys($this->segments))) {
            unset($this->segments[$pos]);

            if ($reindex_after_remove) {
                $this->segments = array_values($this->segments);
            }

            return $this;
        } else {
            throw new OutOfRangeException('Refering to non existent Segment position!');
        }
    }

    /**
     * from: Reads the first parameter which can be a String, PHP array, JSON
     * array and creates + appends Segments from it in one step.
     *
     * Supports method chaining.
     *
     * @param mixed $input Either: PHP array, JSON array, URI string
     * @return \Noherczeg\Breadcrumb\Breadcrumb
     * @throws InvalidArgumentException
     */
    public function from($input = null)
    {
        $guaranteed_array = $this->inputToArray($input);

        // append all
        foreach ($guaranteed_array as $segment_raw_name) {
            $this->append($segment_raw_name);
        }

        // chaining support :)
        return $this;
    }

    private function inputToArray ($input = null)
    {
        $this->checkFromArgs($input);

        // PHP array
        $guaranteed_array = $this->safeArrayAssignment($input);

        // JSON array
        if (is_string($input) && json_decode($input) != null) {
            $guaranteed_array = array_values((array) json_decode($input));

        // URI string
        } elseif (is_string($input)) {
            $guaranteed_array = preg_split('/\//', $input, -1, PREG_SPLIT_NO_EMPTY);
        }

        return $guaranteed_array;
    }

    /**
     * @param $input
     * @throws \InvalidArgumentException
     */
    private function checkFromArgs($input)
    {
        if (!is_string($input) && !is_array($input))
            throw new InvalidArgumentException("Invalid argument provided, string/array required!");
    }

    /**
     * @param $input mixed
     * @return array
     * @throws \InvalidArgumentException
     */
    private function safeArrayAssignment($input)
    {
        if (is_array($input) && empty($input)) {
            throw new InvalidArgumentException("Not empty array required!");
        }

        return $input;
    }

    /**
     * Registers a list of title => link pairs with the package.
     *
     * All of the given data will be used as-is no translation, no URL conversion
     * will be applied!
     *
     * @param array $rawArray Array with title => link pairs
     * @return \Noherczeg\Breadcrumb\Breadcrumb
     */
    public function map(array $rawArray)
    {
        $map = new Map($rawArray);
        $this->segments = $map->getSegments();

        return $this;
    }

    /**
     * num_of_segments: Returns the number of segments which are registered
     * in the system.
     *
     * @return int
     */
    public function num_of_segments()
    {
        return count($this->segments);
    }

    /**
     * registered: Returns all the registered Segments.
     *
     * @return array
     */
    public function registered()
    {
        return $this->segments;
    }

    /**
     * segment: A getter which returns the Segment which is at the given
     * position.
     *
     * @param String $id The ID of the required Segment.
     * @return Segment
     * @throws OutOfRangeException
     */
    public function segment($id)
    {
        if (in_array($id, array_keys($this->segments))) {
            return $this->segments[$id];
        } else {
            throw new OutOfRangeException("Invalid argument provided, no segment is present with id: $id!");
        }
    }
    
    /**
     * replace: replaces the information of an existing segment
     *
     * @param $id String $id The ID of the required Segment.
     * @param mixed $raw_name set it to null to keep the old url or
     *                        set name of the appendable Segment
     * @param boolean $base true if it is referring to the base url
     * @param mixed $translate Set to true if you want to use the provided dictionary,
     *                         set to false if you want to skip translation, or
     *                         set to a specific string to assign that value
     * @param bool $disabled
     * @return \Noherczeg\Breadcrumb\Breadcrumb
     * @throws \OutOfRangeException
     */
    public function replace($id, $raw_name = null, $base = false, $translate = true, $disabled = false)
    {
        if (in_array($id, array_keys($this->segments))) {

            if (is_null($raw_name)) {
                $segment = $this->segments[$id];
            } else {
                $segment = new Segment($raw_name, $base, $disabled);
            }

            // if translation is set
            if ($translate) {
                if (is_string($translate) && strlen($translate) > 0) {
                    // we can set(override) the value manually
                    $segment->setTranslated($translate);
                } elseif (is_bool($translate)) {
                    // or use the translator service to do it from a selected Dictionary
                    $segment->setTranslated($this->translator->translate($raw_name));
                }
            } else {
                $segment->setTranslated($raw_name);
            }

            // overwrite the segment
            $this->segments[$id] = $segment;

            return $this;
        } else {
            throw new OutOfRangeException("Invalid argument provided, no segment is present with id: $id!");
        }
    }

    /**
     * Builder method which returns with a result type as required.
     * Supports separator switching, casing switching, and custom property
     * insertion from an array (only if output is set to html!).
     *
     * @param String $format Format of the output
     * @param String|null $casing Casing of Segments
     * @param bool $last_not_link
     * @param String|null $separator Separator String (not there in Foundation!)
     * @param array $customizations Array of properties (only in HTML!)
     * @param bool $different_links
     * @throws \OutOfRangeException
     * @return String
     */
    public function build($format = null, $casing = null, $last_not_link = true, $separator = null, $customizations = array(), $different_links = false)
    {
        $format = (is_null($format)) ? $this->config->value('output_format') : $format;

        if (in_array($format, $this->build_formats)) {

            // compose the namespaced name of the builder which we wanted to use
            $builder_name = '\\Noherczeg\\Breadcrumb\\Builders\\' . ucfirst($format) . 'Builder';

            // instantiate it
            $this->builder_instance = new $builder_name($this->segments, $this->base_url, $this->config);

            // return with the results :)
            return $this->builder_instance->build($casing, $last_not_link, $separator, $customizations, $different_links);
        } else {
            throw new OutOfRangeException("Provided output format($format) is not supported!");
        }
    }
}
