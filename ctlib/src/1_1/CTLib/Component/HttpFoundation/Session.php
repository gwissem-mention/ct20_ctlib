<?php
namespace CTLib\Component\HttpFoundation;

use CTLib\Util\Arr;

/**
 * Custom Session class that includes several convenience methods.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class Session extends \Symfony\Component\HttpFoundation\Session\Session
{

    const DYNAPART_PRESET_KEY = 'dynapartPreSetParameters';   


    /**
     * Proxy for Session::set with added ttl functionality.
     *
     * @param string $key
     * @param mixed $value
     * @param integer $ttl      Value will expire in $ttl minutes. If 0, value
     *                          won't expire.
     *
     * @return Session
     */
    public function set($key, $value, $ttl=0)
    {
        parent::set($key, $value);
        if ($ttl > 0) {
            parent::set($this->formatTtlKey($key), time() + $ttl * 60);
        }
        return $this;
    }

    /**
     * Allows setting of multiple values as once.
     *
     * @param array $values     Pass as array($key => $value).
     * @param integer $ttl      Value will expire in $ttl minutes. If 0, value
     *                          won't expire.
     *
     * @return Session
     */
    public function multiSet($values, $ttl=0)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return $this;
    }

    /**
     * Proxy for Session::get but takes into account custom ttl functionality.
     *
     * @param string $key
     *
     * @return mixed        Returns NULL if $key not found or value expired.
     */
    public function get($key, $default=null)
    {
        $value = parent::get($key, $default);

        if (is_null($value)) { return $value; }

        $ttlKey = $this->formatTtlKey($key);
        $ttl = parent::get($ttlKey);

        if (is_null($ttl)) { return $value; }

        if (time() <= $ttl) {
            return $value;
        } else {
            $this->multiRemove($key, $ttlKey);
            return null;
        }
    }

    /**
     * Functions like AppSession::get but will throw exception if $key not found.
     *
     * @param string $key
     *
     * @return mixed
     * @throws Exception    If $key not found.
     */
    public function mustGet($key)
    {
        $value = $this->get($key);
        if (is_null($value)) {
            throw new \Exception("$key not set in session");
        }
        return $value;
    }

    /**
     * Indicates whether value for key exists in session.
     *
     * @param string $key
     *
     * @return boolean
     */
    public function has($key)
    {
        return ! is_null($this->get($key));
    }

    /**
     * Proxy for Session::remove but supports removing multiple keys at once.
     *
     * @param string $key ,...   Can also pass multiple keys as an array.
     * @return Session
     */
    public function remove($key)
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $key) {
            parent::remove($key);
            parent::remove($this->formatTtlKey($key));
        }
        return $this;
    }

    /**
     * Adds route name into session's visited route history.
     *
     * @param string $routeName
     * @return Session
     */
    public function addToRouteHistory($routeName)
    {
        $routeHistory = $this->get('routeHistory');

        if (count($routeHistory) == 2) {
            array_shift($routeHistory);
        }
        $routeHistory[] = $routeName;
        $this->set('routeHistory', $routeHistory);
        return $this;
    }

    /**
     * Returns route name last visited by session.
     *
     * @return string|null  Returns null if this is first visited route.
     */
    public function getLastVisitedRouteName()
    {
        $routeHistory = $this->get('routeHistory');
        if (count($routeHistory) != 2) { return null; }
        return $routeHistory[0];
    }

    /**
     * Sets last request time.
     *
     * @param integer $time
     * @return Session
     */
    public function setLastRequestTime($time)
    {
        $this->set('lastRequestTime', $time);
        return $this;
    }

    /**
     * Returns last time this session made request.
     *
     * @return integer
     */
    public function getLastRequestTime()
    {
        return $this->get('lastRequestTime');
    }

    /**
     * Sets last login time.
     *
     * @param integer $time
     * @return Session
     */
    public function setLastLoginTime($time)
    {
        $this->set('lastLoginTime', $time);
        return $this;
    }

    /**
     * Returns last time this member logged in to site.
     *
     * @return integer|null
     */
    public function getLastLoginTime()
    {
        return $this->get('lastLoginTime');
    }

    /**
     * Returns timezone name used by this session.
     *
     * @return DateTimeZone|null
     * @throws Exception If timezone is unrecognized by DateTimeZone.
     */
    public function getTimezone()
    {
        if ($timezone = $this->get('timezone')) {
            return new \DateTimeZone($timezone);
        } else {
            return null;
        }
    }

    /**
     * 
     */
    public function setDynapartPreSetParameters($dynaPartName, $dynaPartId,
        array $parameters)
    {
        $this->set(self::DYNAPART_PRESET_KEY, array(
            $dynaPartName => array(
                $dynaPartId => $parameters
            )
        ));
    }

    public function getDynapartPreSetParameters($dynaPartName, $dynaPartId)
    {
        $parameters = $this->get(self::DYNAPART_PRESET_KEY);

        if (empty($parameters)) { return array(); }

        return Arr::findByKeyChain(
            $parameters,
            array($dynaPartName, $dynaPartId)
        );
    }

    public function clearDynapartPreSetParameters($dynaPartName)
    {
        $parameters = $this->get(self::DYNAPART_PRESET_KEY);
        unset($parameters[$dynaPartName]);
        $this->set(self::DYNAPART_PRESET_KEY, $parameters);
    }

    /**
     * Formats custom ttl key for its respective value key.
     *
     * @param string $key
     *
     * @return string
     */
    protected function formatTtlKey($key)
    {
        return "_{$key}_ttl";
    }

   
}