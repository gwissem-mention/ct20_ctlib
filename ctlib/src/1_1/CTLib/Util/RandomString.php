<?php
namespace CTLib\Util;

/**
 * Creates random string for auto-passwords, shared secrets, salts, etc.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class RandomString
{
    const TYPE_ALPHA_UPPER      = 1;
    const TYPE_ALPHA_LOWER      = 2;
    const TYPE_NUMBER           = 4;
    const TYPE_SYMBOL_BASIC     = 8;
    const TYPE_SYMBOL_COMPLEX   = 16;

    const TYPE_ALPHA_ALL        = 3;
    const TYPE_SYMBOL_ALL       = 24;
    


    protected $masterTypes = array(
        self::TYPE_ALPHA_UPPER => array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'),
        self::TYPE_ALPHA_LOWER => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
        self::TYPE_NUMBER => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9),
        self::TYPE_SYMBOL_BASIC => array('!', '@', '#', '$', '%', '^', '&', '*'),
        self::TYPE_SYMBOL_COMPLEX => array('-', '_', '+', '=', ',', '.', ':', ';', '?', '|')
    );

    /**
     * @var array
     */
    protected $types;
    
    /**
     * @param integer $allowedTypeBits  Bit mask of character types allowed when
     *                                  creating random string. If null passed,
     *                                  will use all types.
     */
    public function __construct($allowedTypeBits=null)
    {
        $this->types = $this->pruneTypes($allowedTypeBits);

        if (! $this->types) {
            throw new \Exception('$allowedTypeBits cannot exclude all types');
        }
    }

    /**
     * Creates random string.
     *
     * @param integer $length   Length of random string to create.
     * @return string
     */
    public function create($length)
    {
        if (! is_int($length) || $length <= 0) {
            throw new \Exception('$length must be integer greater than 0');
        }

        $str = '';
        for ($i = 1; $i <= $length; $i++) {
            $typeChars = $this->types[array_rand($this->types)];
            $str .= $typeChars[array_rand($typeChars)];
        }
        return $str;
    }

    /**
     * Prunes master types into set of allowed types.
     *
     * @param integer $allowedTypeBits  Bit mask of character types allowed when
     *                                  creating random string. If null passed,
     *                                  will use all types.
     * @return array
     */
    protected function pruneTypes($allowedTypeBits=null)
    {
        if (is_null($allowedTypeBits)) {
            return array_values($this->masterTypes);
        }
        
        if (! is_int($allowedTypeBits) || $allowedTypeBits < 0) {
            throw new \Exception('$allowedTypeBits must be unsigned integer');
        }

        $allowedTypes = array();
        foreach ($this->masterTypes AS $typeBit => $typeChars) {
            if ($allowedTypeBits & $typeBit) {
                $allowedTypes[] = $typeChars;
            }
        }
        return $allowedTypes;
    }
}