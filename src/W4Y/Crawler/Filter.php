<?php
namespace W4Y\Crawler;

/**
 * Filter
 * 
 * Validate if given strings match a certain criteria.
 * With this class you can validate if a given string contains a specific phrase
 * or if the string matches a regular expression.
 * 
 * @author Ilan Rivers <ilan@webb4you.com>
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
            'allowByFilter' => $dt['allow'],
            'function'      => $dt['name'],
            'value'         => $match
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
        $name = false;
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
                $allow = true;
                break;
            
            default:
        }
        
        if (!$name) {
            throw new \Exception('Unrecognized Filter Type given.');
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
        // Assume it is valid unless otherwise proven
        $isValid = true;

        // Filter the string
        foreach ($this->filters as $name => $vals) {

            switch ($vals['function']) {

                case 'strpos':

                    $result = stripos($string, $vals['value']);
                    
                    if ($vals['allowByFilter'] && $result === false) {
                        $isValid = false;
                    } else if (!$vals['allowByFilter'] && $result !== false) {
                        $isValid = false;
                    }
                    break;

                case 'regex':

                    preg_match($vals['value'], $string, $matches);

                    if ($vals['allowByFilter'] && empty($matches)) {
                        $isValid = false;
                    } else if (!$vals['allowByFilter'] && !empty($matches)) {
                        $isValid = false;
                    }
                    break;
            }
        }
        
        return $isValid;
    }
}