# Rendition Factory for video-input modules (wip)

## Common options

### `enabled` (optional)

Used to disable a whole module from the build chain.

__default__: true

### `format` (mandatory)

A format defines the output file :
- family (image, video, audio, animation, document, unknown)
- mime type (unique mime type for this type of file)
- extension (possible extenstion(s) for this type of file)

For a specific module, only a subset of formats may be available, e.g.:
Since `video_to_frame` extracts one image from the video, the only supported output format(s)
are ones of family=image.

see below "Output formats" for the list of available formats.

### `extension` (optional)

For the file formats that support multiple extensions, e.g.:
`image/jpeg` : [`jpg`, `jpeg`], the prefered extension can be set to override the default (first) one.

__default__: first value in the list of extensions.

### `timeout` (optional)

maximum duration of the ffmpeg command in seconds.

__default__: 3600 seconds

### `threads` (optional)

set the number of threads used by ffmpeg.

__default__: depends on cpu (usually high), so the setting in most usefull to limit the cpu usage 

## Common options for video output formats

### `video_kilobitrate`, `audio_kilobitrate` (optionals - advanced -)

For video and audio output formats, change bitrate.

__default__: depends on the output format.

### `video_codec`, `audio_codec`, `passes` (optionals - advanced -)

Video output formats use internaly a "ffmpeg-format" which itself may support multiple codecs.

e.g. `video-mpeg4` uses ffmpeg-format `X264`, which supports many audio codecs like `aac`, `libmp3lame`, ...

One can change the default ffmpeg codec(s) by setting `video_codec` and/or `audio_codec`.

__default__: depends on the output format, if it uses internally a "ffmpeg-format" like X264, Ogg, ...



--------------------------------------------

# Modules

## video_to_frame
Extracts a frame (image) from a video.

- `from_seconds` time in the video where the frame is extracted.

```yaml
# example
video:
    normalization: ~
    transformations:
        -
            module: video_to_frame
            enabled: true
            options:
                timeout: 3600
                threads: 4
                format: image-jpeg
                from_seconds: 4
                extension: jpeg
```

## video_to_animation
Build an animation from a video.

- `from_seconds` time in the video where the animation begins.
- `duration` duration of the animation in seconds.
- `fps` frames per second of the animation.
- `width`, `height` size of the animation (see below "resize modes").
- `mode` default to `inset` (see below "resize modes").

```yaml
            module: video_to_animation
            options:
                format: animated-gif
                from_seconds: 25
                duration: 5
                fps: 5
                width: 200
                height: 100
                mode: inset
```

## video_summary
Build a video made from extracts of the input video.

- `period` period in seconds between each extract.
- `duration` duration of each extract in seconds.

```yaml
            module: video_summary
            options:
                format: video-quicktime
                period: 30
                duration: 2
```

## ffmpeg
Generic module to chain ffmpeg "filters" in a single command.

- `filters` list of ffmpeg filters to apply. 

Each "filter" has a name and a list of specific options.

```yaml
            module: ffmpeg
            options:
                format: video-quicktime
               filters:
                    -    
                        name: resize
                        width: 320
                        height: 240
                        mode: inset
                    -
                        name: watermark
                        # only local files are supported for now
                        path: "/var/workspace/my_watermarks/google_PNG.png"
                        position: relative
                        bottom: 50
                        right: 50
```


--------------------------------------------

## Output formats

| format          | family    | mime type        | extension(s) |
|-----------------|-----------|------------------|--------------|
| animated-gif    | Animation | image/gif        | gif          |                                
| animated-png    | Animation | image/png        | apng, png    |                                 
| animated-webp   | Animation | image/webp       | webp         |                                 
| image-jpeg      | Image     | image/jpeg       | jpg, jpeg    |
| video-mkv       | Video     | video/x-matroska | mkv          |
| video-mpeg4     | Video     | video/mp4        | mp4          |
| video-mpeg      | Video     | video/mpeg       | mpeg         |
| video-quicktime | Video     | video/quicktime  | mov          |
| video-webm      | Video     | video/webm       | webm         |

--------------------------------------------

## Resize modes
### `inset`
The output is garanteed to fit in the requested size (width, height) and the aspect ratio is kept.
- If only one dimension is provided, the other is computed.
- If both dimensions are provided, the output is resize so the biggest dimension fits into the rectangle.
- If no dimension is provided, the output is the same size as the input.
