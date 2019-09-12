<?php

namespace Pbmedia\LaravelFFMpeg;

use FFMpeg\Format\VideoInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Pbmedia\LaravelFFMpeg\SegmentedExporter;

class HLSPlaylistExporter extends MediaExporter
{
    protected $segmentedExporters = [];

    protected $playlistPath;

    protected $segmentLength = 10;

    protected $saveMethod = 'savePlaylist';

    protected $progressCallback;

    protected $sortFormats = true;

    public function addFormat(VideoInterface $format, callable $callback = null): MediaExporter
    {
        $segmentedExporter = $this->getSegmentedExporterFromFormat($format);

        if ($callback) {
            $callback($segmentedExporter->getMedia());
        }

        $this->segmentedExporters[] = $segmentedExporter;

        return $this;
    }

    public function dontSortFormats()
    {
        $this->sortFormats = false;

        return $this;
    }

    public function getFormatsSorted(): array
    {
        return array_map(function ($exporter) {
            return $exporter->getFormat();
        }, $this->getSegmentedExportersSorted());
    }

    public function getSegmentedExportersSorted(): array
    {
        if ($this->sortFormats) {
            usort($this->segmentedExporters, function ($exportedA, $exportedB) {
                return $exportedA->getFormat()->getKiloBitrate() <=> $exportedB->getFormat()->getKiloBitrate();
            });
        }

        return $this->segmentedExporters;
    }

    public function setPlaylistPath(string $playlistPath): MediaExporter
    {
        $this->playlistPath = $playlistPath;

        return $this;
    }

    public function setSegmentLength(int $segmentLength): MediaExporter
    {
        $this->segmentLength = $segmentLength;

        foreach ($this->segmentedExporters as $segmentedExporter) {
            $segmentedExporter->setSegmentLength($segmentLength);
        }

        return $this;
    }

    protected function getSegmentedExporterFromFormat(VideoInterface $format): SegmentedExporter
    {
        $media = clone $this->media;

        return (new SegmentedExporter($media))
            ->inFormat($format);
    }

    public function getSegmentedExporters(): array
    {
        return $this->segmentedExporters;
    }

    public function onProgress(callable $callback)
    {
        $this->progressCallback = $callback;

        return $this;
    }

    private function getSegmentedProgressCallback($key): callable
    {
        return function ($video, $format, $percentage) use ($key) {
            $previousCompletedSegments = $key / count($this->segmentedExporters) * 100;

            call_user_func($this->progressCallback,
                $previousCompletedSegments + ($percentage / count($this->segmentedExporters))
            );
        };
    }

    public function prepareSegmentedExporters()
    {
        foreach ($this->segmentedExporters as $key => $segmentedExporter) {
            if ($this->progressCallback) {
                $segmentedExporter->getFormat()->on('progress', $this->getSegmentedProgressCallback($key));
            }

            $segmentedExporter->setSegmentLength($this->segmentLength);
        }

        return $this;
    }

    protected function exportStreams()
    {
        $this->prepareSegmentedExporters();

        foreach ($this->segmentedExporters as $key => $segmentedExporter) {
            $segmentedExporter->saveStream($this->playlistPath);
        }
    }

    protected function getMasterPlaylistContents(): string
    {
        $lines = ['#EXTM3U'];

        $segmentedExporters = $this->sortFormats ? $this->getSegmentedExportersSorted() : $this->getSegmentedExporters();

        foreach ($segmentedExporters as $segmentedExporter) {
            $bitrate = $segmentedExporter->getFormat()->getKiloBitrate() * 1000;

            $lines[] = '#EXT-X-STREAM-INF:BANDWIDTH=' . $bitrate;
            $lines[] = $segmentedExporter->getPlaylistFilename();
        }

        return implode(PHP_EOL, $lines);
    }

    public function savePlaylist($playlistData): MediaExporter
    {
        $playlistPath = is_string($playlistData) ? $playlistData : $playlistData['full_path'];

        $this->setPlaylistPath($playlistPath);
        $this->exportStreams();

        if ($this->getDisk()->isLocal()) {
            file_put_contents(
                $playlistPath, $this->getMasterPlaylistContents()
            );

            return $this;
        }

        $playlistTitle = pathinfo($playlistPath, PATHINFO_FILENAME);

        $exportDir = pathinfo($playlistPath, PATHINFO_DIRNAME);

        $subdir = ltrim(Str::after($exportDir, $playlistData['directory']), DIRECTORY_SEPARATOR);

        $subdir = $subdir ? ($subdir . DIRECTORY_SEPARATOR) : null;

        Collection::make(scandir($exportDir))->filter(function ($path) use ($playlistTitle) {
            return Str::contains($path, $playlistTitle);
        })->each(function ($path) use ($subdir, $exportDir) {
            $file = $this->getDisk()->newFile(
                $subdir . pathinfo($path, PATHINFO_BASENAME)
            );

            $this->moveSavedFileToRemoteDisk($exportDir . DIRECTORY_SEPARATOR . $path, $file);
        });

        $this->getDisk()->put($subdir . pathinfo($playlistPath, PATHINFO_BASENAME), $this->getMasterPlaylistContents());

        return $this;
    }

    protected function getDestinationPathForSaving(File $file)
    {
        if ($file->getDisk()->isLocal()) {
            return parent::getDestinationPathForSaving($file);
        }

        $temporaryDirectory = FFmpeg::newTemporaryDirectory();

        $fullPath = $temporaryDirectory . DIRECTORY_SEPARATOR . $file->getPath();

        if (!is_dir($fullDir = pathinfo($fullPath, PATHINFO_DIRNAME))) {
            mkdir($fullDir, 0755, true);
        }

        return [
            'path'      => $file->getPath(),
            'directory' => $temporaryDirectory,
            'full_path' => $fullPath,
        ];
    }
}
