<?php
namespace CTLib\Component\MSTeams;

use CTLib\Component\MSTeams\Exception\FieldNotRecognizedException;
use CTLib\Component\MSTeams\Exception\NotAChildException;
use CTLib\Component\MSTeams\Exception\NotAnAttributeException;
use CTLib\Component\MSTeams\MessageCard;
use CTLib\Component\MSTeams\MessageCardSection;

class MessageCardObject implements \JsonSerializable
{
    /** @var array */
    protected $attributes;

    /** @var assoc */
    protected $values;

    /** @var assoc */
    protected $children;

    /**
     * Creates an empty object of this or a child class from an array
     * argument.
     */
    static public function createFromArray($settings)
    {
        $object = new static();
        foreach ($settings as $setting => $values) {
            if ($object->isAttribute($setting)) {
                $object->setAttribute($setting, $values);
            } else if ($object->isChild($setting)) {
                foreach ($values as $value) {
                    $object->createChild($setting, $value);
                }
            }
        }

        return $object;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return array_merge(
            $this->defaults(),
            $this->gatherAttributes(),
            $this->gatherChildren()
        );
    }

    /**
     * Returns the value of the of the attribute thtat is named
     * @param string $name
     * @return string|int
     */
    public function getAttribute($name)
    {
        if ($this->isAttribute($name)) {
            return isset($this->$name) ? $this->$name : $this->getDefault($name);
        } else {
            throw new NotAnAttributeException($name, get_class($this));
        }
    }

    /**
     * Set the attribute on the class useing the passed name and value
     * @param string $name
     * @param string|int $value
     * @return MessageCardObject
     * @throws NotAnAttributeException
     */
    public function setAttribute($name, $value)
    {
        if ($this->isAttribute($name)) {
            $this->$name = $value;
            return $this;
        } else {
            throw new NotAnAttributeException($name, get_class($this));
        }
    }

    /**
     * Returns an array of obects that represent the payload JSON structure
     * @param string name
     * @return array
     * @throws NotAChildException
     */
    public function getChild($name)
    {
        if ($this->isChild($name)) {
            return isset($this->$name) ? $this->$name : [];
        } else {
            throw new NotAChildException($name);
        }
    }

    /**
     * Creates the child record but returns the parent object for chaining
     * @param string $name
     * @param package
     * @returns MessageCardObject
     * @throws NotAChildException
     */
    public function addChild($name, ...$args)
    {
        if ($this->isChild($name)) {
            $this->createChild($name, ...$args);
            return $this;
        } else {
            throw new NotAChildException($name);
        }
    }

    /**
     * Creates a child and then returns that child
     * @param string $name
     * @param package
     * @return MessageCardObject
     * @throw NotAChildException
     */
    public function createChild($name, ...$args)
    {
        if (!$this->isChild($name)) {
            throw new NotAChildException($name);
        }

        $child = $this->children[$name];

        if (is_array($args[0])) {
            var_dump('creating from array');
            $child = $child::createFromArray($args[0]);
        } else {
            $child = new $child(...$args);
        }

        if (!isset($this->$name)) {
            $this->$name = [];
        }

        array_push($this->$name, $child);
        $children[] = $child;

        return $child;
    }

    /**
     * Determines if the passed name is that of a child node
     * @param string $name
     * @return bool
     */
    public function isChild($name)
    {
        return in_array($name, array_keys($this->children ?: []));
    }

    /**
     * Determines if the passed name is that of an attribute node
     * @param string $name
     * @return bool
     */
    public function isAttribute($name)
    {
        return in_array($name, $this->attributes ?: []);
    }

    /**
     * @return array
     */
    protected function gatherAttributes()
    {
        foreach ($this->attributes as $attr) {
            $attrs[$attr] = $this->getAttribute($attr);
        }

        return $attrs;
    }

    /**
     * @return array
     */
    public function gatherChildren()
    {
        $children = [];

        foreach ($this->children ?: [] as $child => $class) {
            $children[$child] = $this->getChild($child);
        }

        return $children;
    }


    /**
     * Fallthrough method for getting and setting children and attributes
     * @param string
     * @param package
     * @return MessageCardObject
     */
    public function __call($name, $args)
    {
        if (strpos($name, 'set') === 0) {
            return $this->tryToSet($name, $args[0]);
        } else if (strpos($name, 'get') === 0) {
            return $this->tryToGet($name);
        } else if (strpos($name, 'create') === 0) {
            return $this->tryToCreateChild($name, $args);
        } else if (strpos($name, 'add') === 0) {
            return $this->tryToAddChild($name, $args);
        }
    }

    /**
     * attempts to determine the name of a child or attribute from the fallthrough
     * method and then apply it as asked
     * @param string $name
     * @param mixed $value
     * @return MessageCardObject
     * @throws FieldNotRecognizedException
     */
    protected function tryToSet($name, $value)
    {
        $attr = lcfirst(substr($name, 3));
        if ($this->isChild($attr)) {
            return $this->addChild($attr, $value);
        } else if ($this->isAttribute($attr)) {
            return $this->setAttribute($attr, $value);
        }

        throw new FieldNotRecognizedException($attr, get_class($this));
    }

    /**
     * Seeks out the named attribute or child
     * @param string name
     * @return MessageCardObject
     * @throws FieldNotRecognizedException
     */
    protected function tryToGet($name)
    {
        $attr = lcfirst(substr($name, 3));
        if ($this->isChild($attr)) {
            return $this->getChild($attr);
        } else if ($this->isAttribute($attr)) {
            return $this->getAttribute($attr);
        }

        throw new FieldNotRecognizedException($attr, get_class($this));
    }

    /**
     * attempts to "create" a child
     * @param string $name
     * @param mixed $args
     * @return MessageCardObject
     */
    protected function tryToCreateChild($name, $args)
    {
        $name = lcfirst(substr($name, 6)) . 's';
        return $this->createChild($name, ...$args);
    }

    /**
     * attempts to "add" a child
     * @param string $name
     * @param mixed $args
     * @return MessageCardObject
     */
    protected function tryToAddChild($name, $args)
    {
        $name = lcfirst(substr($name, 3)) . 's';
        return $this->addChild($name, ...$args);
    }

    /**
     * The defaults that will be used for this object at the time of JSON
     * conversion if no other value is available
     * @return array
     */
    protected function defaults()
    {
        return [];
    }

    /**
     * Gets the default value for the named attribute
     * @param string name attribute
     */
    protected function getDefault($default)
    {
        if (isset($this->defaults()[$default])) {
            return $this->defaults()[$default];
        }
        return null;
    }
}

