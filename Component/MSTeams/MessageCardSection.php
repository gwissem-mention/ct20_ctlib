<?php
namespace CTLib\Component\MSTeams;

/**
 * Defines a notification to be posted to a MSTeams channel.
 *
 * You can find related information about configuring the Microsoft Teams
 * message cards by going to:
 * https://docs.microsoft.com/en-us/outlook/actionable-messages/card-reference
 */
class MessageCardSection extends MessageCardObject
{
    /**
     * @param string $title - Only required entry
     */
    public function __construct($title = '', $text = '')
    {
        $this->attributes = ['title', 'text'];
        $this->children = ['facts' => 'CTLib\Component\MSTeams\MessageCardSectionFact'];
        $this->setTitle($title);
        $this->setText($text);
    }

    /**
     * {@inheritdoc }
     */
    protected function defaults()
    {
        return [
            'markdown'  => true
        ];
    }
}

