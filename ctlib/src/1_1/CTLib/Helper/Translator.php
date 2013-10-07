<?php
namespace CTLib\Helper;

use Symfony\Component\Translation\MessageSelector,
    CTLib\Util\Util;

/**
 * Translator
 * Inherits from Symfony's Translator and adds custom methods.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class Translator extends \Symfony\Bundle\FrameworkBundle\Translation\Translator
{

    /**
     * Returns fallback locale.
     *
     * @return string
     */
    public function getFallbackLocale()
    {
        // MT @ May 21, 2013: Hardcoding to 'en_US' because its purpose in
        // retrieving string translations in database won't work with Symfony's
        // fallback locale of 'en'. Currently, cannot switch Symfony's fallback
        // locale to 'en_US' because it causes problem with framework.
        return 'en_US';
    }

    /**
     * Returns MessageCatalogue for given $locale.
     *
     * @param string $locale
     *
     * @return MessageCatalogue
     * @throws Exception        If catalog does not exist for $locale.
     */
    public function getCatalog($locale)
    {
        if (! isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);

            if (! isset($this->catalogues[$locale])) {
                throw new \Exception("Catalog not found for locale: $locale");
            }
        }
        return $this->catalogues[$locale];
    }

    /**
     * Returns messages hash for given $domain and $locale.
     *
     * @param string $domain
     * @param string $locale    If null, will use session's locale.
     *
     * @return array            array(messageId => translation, ...)
     */
    public function getDomainMessages($domain='messages', $locale=null)
    {
        $locale = $locale ?: $this->getLocale();
        return $this->getCatalog($locale)->all($domain);
    }

    /**
     * Returns subset of domain messages that start with $prefix.
     *
     * @param string $prefix        I.e., 'activity.status'
     * @param string $domain
     * @param string $locale        If null, will use session's locale.
     *
     * @return array                array(messageId => translation, ...)
     */
    public function getMessages($prefix, $domain='messages', $locale=null)
    {
        $prefix     = Util::append($prefix, '.');
        $messages   = $this->getDomainMessages($domain, $locale);
        $messageIds = array_filter(
            array_keys($messages),
            function ($messageId) use ($prefix) {
                return strpos($messageId, $prefix) === 0;
            }
        );
        if (! $messageIds) { return array(); }

        return array_intersect_key($messages, array_flip($messageIds));
    }

    /**
     * Shortcut to Translator::trans.
     *
     * @param string    $id             Message identifier.
     * @param array     $parameters     String substitutions.
     * @param string    $domain         Message domain.
     * @param string    $locale         Locale to translate into.
     *
     * @return string
     */
    public function trans($id, array $parameters=array(), $domain='messages',
        $locale=null)
    {
        return parent::trans(
            $id,
            $this->encapsTransParameters($parameters),
            $domain,
            $locale);
    }

    /**
     * Shortcut to Translator::transChoice.
     *
     * @param string    $id             Message identifier.
     * @param int       $number         Determines pluralization form.
     * @param array     $parameters     String substitutions.
     * @param string    $domain         Message domain.
     * @param string    $locale         Locale to translate into.
     *
     * @return string
     */
    public function transChoice($id, $number, array $parameters=array(),
        $domain='messages', $locale=null)
    {
        return parent::transChoice(
            $id,
            $number,
            $this->encapsTransParameters($parameters),
            $domain,
            $locale);
    }

    /**
     * Simple translation that assumes no parameters and translation found in
     * messages domain.
     *
     * @param string    $id             Message identifier.
     * @param string    $locale         Locale to translate into.
     *
     * @return string
     */
    public function transSimple($id, $locale=null)
    {
        return parent::trans($id, array(), 'messages', $locale);
    }

    /**
     * Helper method to properly encapsulate translation parameters with '%'.
     *
     * @param array $parameters     array(param => value)
     * @return array
     */
    protected function encapsTransParameters($parameters)
    {
        if (! $parameters) { return $parameters; }

        return array_combine(
            array_map(
                function($param) { return '%' . trim($param, '%') . '%'; },
                array_keys($parameters)
            ),
            array_values($parameters)
        );
    }


}