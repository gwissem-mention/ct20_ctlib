<?php
namespace CTLib\Helper;


class JavascriptHelper
{
    protected $translator;
    protected $routeInspector;
    protected $translations;
    protected $values;
    protected $routes;

    public function __construct($translator, $routeInspector, $authorization=null)
    {
        $this->translator       = $translator;
        $this->routeInspector   = $routeInspector;
        $this->authorization    = $authorization;
        $this->translations     = array();
        $this->values           = array();
        $this->routes           = array();
        $this->permissions      = array();
    }

    /**
     * Adds translation for use in Javascript.
     *
     * @param string $messageId,... Can also send multiple as array.
     *                              If $messageId ends with '*', will add all
     *                              translations with messageIds that begin
     *                              with $messageId.  For example, if
     *                              'activity.status*' is passed, will add
     *                              translations for 'activity.status.INPRG',
     *                              'activity.status.FNSHD', etc.
     * @return JavascriptHelper
     */
    public function addTranslation($messageId)
    {
        $messageIds = is_array($messageId) ? $messageId : func_get_args();

        foreach ($messageIds AS $messageId) {
            if (substr($messageId, -1) == '*') {
                $this->addWildcardTranslation($messageId);
            } else {
                $this->addSingleTranslation($messageId);
            }
        }
        return $this;        
    }

    /**
     * Adds manual translation (one that doesn't rely on the built-in 
     * translation mechanism) for use in Javascript.
     * 
     * if the parameter of $translation is a string. it will be direct put
     * into translation under key given by $messageId. if the $translation
     * is an array, it will take all values given by key of $translationFieldName
     * in the array.
     *
     * @param string $messageId
     * @param string $translation
     *
     * @return JavascriptHelper
     */
    public function addManualTranslation($messageId, $translation, $translationFieldName = "name")
    {
        if (is_string($translation)) {
            $this->registerTranslation($messageId, $translation);
            return $this;
        }

        if (is_array($translation)) {
            $javascriptHelper = $this;
            array_walk(
                $translation,
                function($val, $key) use($messageId, $javascriptHelper, $translationFieldName) {
                    $javascriptHelper->addManualTranslation($messageId.".".$key, $val[$translationFieldName]);
                }
            );
            return $this;
        }

        throw new \Exception('the parameter of $translation is invalid');
    }

    /**
     * Helper to add wildcard translation ($messageId ends with '*');
     *
     * @param string $messageId
     *
     * @return void
     * @throws Exception    If translations not found for wildcard.
     */
    protected function addWildcardTranslation($messageId)
    {
        $prefix     = rtrim($messageId, '*');
        $messages   = $this->translator->getMessages($prefix);

        if (! $messages) {
            throw new \Exception("No translations found for messageId: $messageId");
        }

        foreach ($messages AS $messageId => $translation) {
            $this->registerTranslation($messageId, $translation);
        }
    }

    /**
     * Helper to add single translation.
     *
     * @param string $messageId
     *
     * @return void
     * @throws Exception    If translation not found for $messageId.
     */
    protected function addSingleTranslation($messageId)
    {
        $translation = $this->translator->trans($messageId);
        if ($translation == $messageId) {
            throw new \Exception("No translation found for messageId: $messageId");
        }
        $this->registerTranslation($messageId, $translation);
    }

    /**
     * Registers translation.
     *
     * @param string $messageId
     * @param string $translation
     *
     * @return void
     * @throws Exception    If translation already set for $messageId.
     */
    protected function registerTranslation($messageId, $translation)
    {
        if (isset($this->translations[$messageId])) {
            throw new \Exception("Translation already set for messageId: $messageId");
        }
        $this->translations[$messageId] = $translation;
    }

    /**
     * Adds route pattern URL for use in Javascript.
     *
     * @param string $routeName,... Can also send multiple routes as array.
     *
     * @return JavascriptHelper
     * @throws Exception    If route already set for $routeName.
     */
    public function addRoute($routeName)
    {
        $routeNames = is_array($routeName) ? $routeName : func_get_args();

        foreach ($routeNames AS $routeName) {
            if (isset($this->routes[$routeName])) {
                throw new \Exception("Route already set for routeName: $routeName");
            }

            $this->routes[$routeName] = $this
                                        ->routeInspector
                                        ->getPattern($routeName);
        }
        return $this;
    }

    /**
     * Sets key/value pair for use in Javascript.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return JavascriptHelper
     * @throws Exception    If value already set for $key.
     */
    public function set($key, $value)
    {
        //if (isset($this->values[$key])) {
        //    throw new \Exception("Value already set for key: $key");
        //}
        $this->values[$key] = $value;
        return $this;
    }

    /**
     * Returns value for key.
     *
     * @param string $key
     * @return mixed        Returns NULL if key not found.
     */
    public function get($key)
    {
        if (! isset($this->values[$key])) { return null; }
        return $this->values[$key];
    }

    /**
     * Sets multiple key/value pairs for use in Javascript.
     *
     * @param array $values     Send as array($key => $value, ...).
     * @return JavascriptHelper
     */
    public function multiSet($values)
    {
        foreach ($values AS $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * Add constant name/values from class for use in Javascript.
     * NOTE: Uses class's short name (no namespace) for key prefix in JS
     * values hash.
     *
     * @param string $className
     * @param string $pattern       Regexp used to restrict constants returned.
     *
     * @return JavascriptHelper 
     */
    public function addConstants($className, $pattern=null)
    {
        $this->multiSet($this->getConstants($className, $pattern));
        return $this;
    }

    /**
     * Add constant name/values along with their translations for use in
     * Javascript.
     *
     * @param string $className
     * @param string $translationPrefix
     * @param string $pattern       Regexp used to restrict constants returned.
     *
     * @return JavascriptHelper 
     */
    public function addConstantsWithTranslations($className, $translationPrefix, $pattern=null)
    {
        foreach ($this->getConstants($className, $pattern) AS $constant => $value) {
            $this->set($constant, $value);
            $this->addTranslation("{$translationPrefix}.{$value}");
        }
        return $this;
    }

    /**
     * Uses reflection to extract constant name/values from a class.
     *
     * @param string $className
     * @param string $pattern       Regexp used to restrict constants returned.
     *
     * @return array                array(ClassName::CONSTANT => value)
     */
    public function getConstants($className, $pattern=null)
    {
        $constants = array();
        $reflection = new \ReflectionClass($className);

        foreach ($reflection->getConstants() AS $constant => $value) {
            if ($pattern && ! preg_match($pattern, $constant)) { continue; }

            $qualifiedName = "{$reflection->getShortName()}::{$constant}";
            $constants[$qualifiedName] = $value;
        }
        return $constants;
    }

    /**
     * Returns translations.
     * @return string
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * Returns key/value pairs.
     * @return string
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Returns routes.
     * @return string
     */
    public function getRoutes()
    {
        return $this->routes;
    }
    
    /**
     * Returns permissions.
     * @return string
     */
    public function getPermissions()
    {
        if ($this->authorization) {
            return array_fill_keys(
                $this->authorization->getObjectPermissions(),
                true
            );
        } else {
            return array();
        }
    }

    /**
     * Add default message Ids
     *
     * @param mixed $messageIds
     * @return void
     */
    public function defaultTranslations($messageIds)
    {
        $messageIds = is_array($messageIds) ? $messageIds : func_get_args();
        foreach ($messageIds as $messageId) {
            $this->addTranslation($messageId);
        }
    }
}
