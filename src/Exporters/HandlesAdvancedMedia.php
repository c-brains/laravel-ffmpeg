<?php

namespace ProtoneMedia\LaravelFFMpeg\Exporters;

use FFMpeg\Format\FormatInterface;
use Illuminate\Support\Arr;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\AdvancedOutputMapping;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\AttachedPicFormat;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;

trait HandlesAdvancedMedia
{
    /**
     * @var \Illuminate\Support\Collection
     */
    protected $maps;

    public function addFormatOutputMapping(FormatInterface $format, Media $output = null, array $outs, $forceDisableAudio = false, $forceDisableVideo = false)
    {
        if ($format instanceof AttachedPicFormat && !$format->getMapping()) {
            $format->setMapping(Arr::first($outs));
        }

        $this->maps->push(
            new AdvancedOutputMapping($outs, $format, $output, $forceDisableAudio, $forceDisableVideo)
        );

        return $this;
    }
}
