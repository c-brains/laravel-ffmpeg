<?php

namespace ProtoneMedia\LaravelFFMpeg\FFMpeg;

use FFMpeg\Format\Audio\DefaultAudio;

class AttachedPicFormat extends DefaultAudio
{
    private $mapping;

    public function __construct(string $mapping = null)
    {
        $this->audioCodec = null;

        $this->audioKiloBitrate = null;

        $this->mapping = $mapping;
    }

    public function getMapping(): ?string
    {
        return $this->mapping;
    }

    public function setMapping(string $mapping): self
    {
        $this->mapping = $mapping;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtraParams()
    {
        return ['-disposition:v:' . $this->mapping, 'attached_pic'];
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableAudioCodecs()
    {
        return [];
    }
}
