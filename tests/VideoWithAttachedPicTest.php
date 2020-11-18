<?php

namespace ProtoneMedia\LaravelFFMpeg\Tests;

use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;
use ProtoneMedia\LaravelFFMpeg\MediaOpener;

class VideoWithAttachedPicTest extends TestCase
{
    /** @test */
    public function it_can_add_a_watermark_with_the_factory_helper_and_manipulate_it()
    {
        $this->fakeLocalVideoFile();
        $this->addTestFile('logo.png');

        (new MediaOpener)->open(['video.mp4', 'logo.png'])
            ->export()
            ->addFormatOutputMapping($this->x264(), Media::make('local', 'new_video.mp4'), ['0'], false, false, function (array $commands) {
                $path = array_pop($commands);

                return array_merge($commands, [
                    '-map', '1', '-c', 'copy', '-c:v:1', 'png', '-disposition:v:1', 'attached_pic', $path,
                ]);
            })
            ->save();

        $streams = (new MediaOpener)->open('new_video.mp4')->getStreams();

        $this->assertCount(3, $streams);
        $this->assertEquals('png', $streams[2]->get('codec_name'));
        $this->assertEquals(1, $streams[2]->get('disposition')['attached_pic']);
    }
}
