<?php
namespace CTLib\Component\MSTeams;

/**
 * Defines a notification to be posted to a MSTeams channel.
 *
 * You can find related information about configuring the Microsoft Teams
 * message cards by going to:
 * https://docs.microsoft.com/en-us/outlook/actionable-messages/card-reference
 */
class MessageCard extends MessageCardObject
{

    /** base colors.*/
    const COLOR_YELLOW  = 'ffff00';
    const COLOR_BLUE    = '0000ff';
    const COLOR_RED     = 'ff0000';
    const COLOR_GREEN   = '00ff00';

    /**
     * @param string $title
     */
    public function __construct($title = '', $text = null)
    {
        $this->attributes = ['title', 'text', 'summary', 'themeColor'];
        $this->children = ['sections' => 'CTLib\Component\MSTeams\MessageCardSection'];
        $this->values = [
            '@type'         => 'MessageCard',
            '@context'      => 'http://schema.org/extensions'
        ];

        $this->setTitle($title);
        $this->setText($text);
    }

    /**
     * Sets $color to yellow.
     * @return MSTeamsChannelNotification
     */
    public function yellow()
    {
        return $this->setThemeColor(self::COLOR_YELLOW);
    }

    /**
     * Sets $color to blue.
     * @return MSTeamsChannelNotification
     */
    public function blue()
    {
        return $this->setThemeColor(self::COLOR_BLUE);
    }

    /**
     * Sets $color to red.
     * @return MSTeamsChannelNotification
     */
    public function red()
    {
        return $this->setThemeColor(self::COLOR_RED);
    }

    /**
     * Sets $color to green.
     * @return MSTeamsChannelNotification
     */
    public function green()
    {
        return $this->setThemeColor(self::COLOR_GREEN);
    }

    /**
     * {@inheritdoc }
     */
    protected function defaults()
    {
        return [
            'themeColor' => self::COLOR_GREEN,
            'summary' => 'Message from Symfony'
        ];
    }
}

