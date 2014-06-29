<?php
namespace W4Y\Crawler;

/**
 * Filter
 *
 * Validate if given strings match a certain criteria.
 * With this class you can validate if a given string contains a specific phrase
 * or if the string matches a regular expression.
 *
 */
class Filter
{
    /** @var string|null */
    private $name = null;

    private $filters = array();

    const MUST_CONTAIN = 1;

    const MUST_NOT_CONTAIN = 2;

    const MUST_MATCH = 3;

    const MUST_NOT_MATCH = 4;

    const FILTER_ALLOW = 'allowByFilter';
    const FILTER_FUNCTION = 'function';
    const FILTER_VALUE = 'value';

    public function __construct($name = null, array $filters = array())
    {
        if (null !== $name) {
            $this->name = $name;
        }

        foreach ($filters as $filter) {
            $this->setFilter($filter['match'], $filter['type']);
        }
    }

    /**
     * Check if a given string can pass the filter test
     *
     * @param string $string
     * @return boolean
     */
    public function isValid($string)
    {
        return $this->filterCheck($string);
    }

    /**
     * Set the name of the filter
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Set a filter rule
     *
     * @param string $match
     * @param int $filterType
     */
    public function setFilter($match, $filterType = self::MUST_CONTAIN)
    {
        $dt = $this->filterTypeToMethod($filterType);

        $this->filters[] = array(
            self::FILTER_ALLOW => $dt['allow'],
            self::FILTER_FUNCTION => $dt['name'],
            self::FILTER_VALUE => $match
        );
    }

    /**
     * Rest the filters
     */
    public function reset()
    {
        $this->filters = array();
    }

    /**
     * Translate a filter type to the correct function and
     * match criteria.
     *
     * @param int $type
     * @return array
     * @throws \Exception
     */
    private function filterTypeToMethod($type)
    {
        $allow = false;
        switch ($type) {

            case self::MUST_CONTAIN:
                $name = 'strpos';
                $allow = true;
                break;

            case self::MUST_NOT_CONTAIN:
                $name = 'strpos';
                $allow = false;
                break;

            case self::MUST_MATCH:
                $name = 'regex';
                $allow = true;
                break;

            case self::MUST_NOT_MATCH:
                $name = 'regex';
                $allow = false;
                break;

            default:
                throw new \Exception(sprintf('%s::Unrecognized Filter Type "%s" given.', __CLASS__, $type));
        }

        return array(
            'name' => $name,
            'allow' => $allow
        );
    }

    /**
     * Filter a string based on the filters that were set.
     *
     * @param string $string
     * @return boolean
     */
    private function filterCheck($string)
    {
        // Assume it is valid unless otherwise proven.
        $isValid = true;

        // Filter the string
        foreach ($this->filters as $vals) {

            switch ($vals['function']) {

                case 'strpos':

                    $result = stripos($string, $vals[self::FILTER_VALUE]);

                    if ($vals[self::FILTER_ALLOW] && $result === false) {
                        $isValid = false;
                    } else if (!$vals[self::FILTER_ALLOW] && $result !== false) {
                        $isValid = false;
                    }
                    break;

                case 'regex':

                    preg_match($vals[self::FILTER_VALUE], $string, $matches);

                    if ($vals[self::FILTER_ALLOW] && empty($matches)) {
                        $isValid = false;
                    } else if (!$vals[self::FILTER_ALLOW] && !empty($matches)) {
                        $isValid = false;
                    }
                    break;
            }
        }

        return $isValid;
    }
}