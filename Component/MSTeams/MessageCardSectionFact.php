<?php
namespace CTLib\Component\MSTeams;

/**
 * Defines a notification to be posted to a MSTeams channel.
 *
 * You can find related information about configuring the Microsoft Teams
 * message cards by going to:
 * https://docs.microsoft.com/en-us/outlook/actionable-messages/card-reference
 */
class MessageCardSectionFact extends MessageCardObject
{
    /**
     * {@inheritdoc }
     */
    public function __construct($name = '', $value = '')
    {
        $this->attributes = ['name', 'value'];
        $this->setName($name);
        $this->setValue($value);
    }
}

