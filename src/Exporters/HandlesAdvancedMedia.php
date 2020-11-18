<?php

namespace ProtoneMedia\LaravelFFMpeg\Exporters;

use FFMpeg\Format\FormatInterface;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\AdvancedOutputMapping;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;

trait HandlesAdvancedMedia
{
    /**
     * @var \Illuminate\Support\Collection
     */
    protected $maps;

    public function addFormatOutputMapping(FormatInterface $format, Media $output, array $outs, $forceDisableAudio = false, $forceDisableVideo = false, callable $withCommands = null)
    {
        $this->maps->push(
            new AdvancedOutputMapping($outs, $format, $output, $forceDisableAudio, $forceDisableVideo, $withCommands)
        );

        return $this;
    }
}
