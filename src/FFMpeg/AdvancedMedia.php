<?php

namespace ProtoneMedia\LaravelFFMpeg\FFMpeg;

use Closure;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Media\AdvancedMedia as MediaAdvancedMedia;
use Illuminate\Support\Arr;

class AdvancedMedia extends MediaAdvancedMedia
{
    use InteractsWithHttpHeaders;

    /**
     * Create a new instance of this class with the instance of the underlying library.
     *
     * @param \FFMpeg\Media\AdvancedMedia $media
     * @return self
     */
    public static function make(MediaAdvancedMedia $media): self
    {
        return new static($media->getInputs(), $media->getFFMpegDriver(), FFProbe::make($media->getFFProbe()));
    }

    public function map(
        array $outs,
        FormatInterface $format,
        $outputFilename,
        $forceDisableAudio = false,
        $forceDisableVideo = false
    ) {
        $getter = Closure::bind(function (MediaAdvancedMedia $media) {
            return $media->mapCommands;
        }, null, MediaAdvancedMedia::class);

        $setter = Closure::bind(function (MediaAdvancedMedia $media, array $commands = []) {
            $media->mapCommands = $commands;
        }, null, MediaAdvancedMedia::class);

        parent::map($outs, $format, $outputFilename, $forceDisableAudio, $forceDisableVideo);

        $commands = $getter($this);

        $setter($this, array_filter($commands, function ($command) {
            return !is_null($command);
        }));

        return $this;
    }

    /**
     * Builds the command using the underlying library and then
     * prepends every input with its own set of headers.
     *
     * @return array
     */
    protected function buildCommand()
    {
        $command = parent::buildCommand();

        $inputKey = array_search(Arr::first($this->getInputs()), $command) - 1;

        foreach ($this->getInputs() as $key => $path) {
            $headers = $this->headers[$key];

            if (empty($headers)) {
                $inputKey += 2;
                continue;
            }

            $command = static::mergeBeforeKey($command, $inputKey, static::compileHeaders($headers));
            $inputKey += 4;
        }

        return $command;
    }
}
