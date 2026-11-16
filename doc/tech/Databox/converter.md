# Conversion container (`databox-converter`)

The tools that read untrusted files — **LibreOffice** (`document_to_pdf`), **ffmpeg** and
**ffprobe** (video and audio renditions, clips of the exports and of the Expose publications) and
**exiftool** (metadata read on ingestion, written on export, `set_dpi`,
`extract_embedded_preview`) — do not run in the databox workers, whose environment carries every
secret of the stack (database, MinIO, Keycloak, RabbitMQ…): a hostile document able to read a local
file (a vulnerable import filter, a link followed by the tool) could otherwise read
`/proc/<pid of the worker>/environ` and hand it back in the rendition.

They run in `databox-converter`, a container:

- without any secret: no application code, no `.env`, only its own variables
  (`CONVERTER_MAX_JOBS`, `CONVERTER_MAX_TIMEOUT`); not built on `databox-api-php`, whose
  environment it would inherit;
- on the `convert` network only (`internal`), shared with the databox workers and schedulers: no
  database, Redis, RabbitMQ, MinIO, Elasticsearch, nor Internet. There is no authentication: the
  network is the boundary, a token would be one more secret;
- read-only (`/tmp` in memory), all capabilities dropped but those it needs to hand the job
  directories over, switch to the uid of a job and kill its processes (`CHOWN`, `DAC_OVERRIDE`,
  `FOWNER`, `SETUID`, `SETGID`, `KILL`), `no-new-privileges`, CPU, memory and pids limits.

## How a command runs

The application configures the binary of each tool with the wrapper of its image
(`/usr/local/libexec/convert/{soffice,ffmpeg,ffprobe,exiftool}`) when `CONVERTER_URL` is set
(`alchemy_rendition_factory.converter_url` and `alchemy_metadata_manipulator.converter_url`):
php-ffmpeg, phpexiftool and `PdfConverter` run it as they would run the tool. The wrapper calls
`convert-exec` (`lib/php/rendition-factory/bin/`, code in
`lib/php/rendition-factory/src/RemoteExec/`), which:

1. creates a job directory, `/var/convert/jobs/<random>` (0700), on the `convert` volume shared
   with the converter, mounted at the same path in both containers;
2. hands the files of the command to the job (`PathMapper`): the worker writes its temporary files
   in `/var/convert/worker` (its `TMPDIR`, 0700, on the same volume), so an existing file is hard
   linked into the job, an output keeps its name in the job directory of its parent, a directory
   (LibreOffice profile, `--outdir`) is mirrored. The exact path, `file://` URLs
   (`-env:UserInstallation=`), `key=/path`, paths in a filter (`movie=` of the video watermark),
   the `-passlogfile` prefix, the list of the ffmpeg concat demuxer and the relative paths to
   existing files (when the command runs in the worker directory) are rewritten; any other
   reference to `/var/convert/worker`, or to an existing file outside of it, makes the command fail
   with an explicit message;
3. sends `{tool, args, cwd, timeout}` to `convert-server` and relays the output and the exit code
   of the tool as they come (stdin is not forwarded: no caller uses it);
4. once the job is over, renames into `/var/convert/worker` the files the tool created or replaced,
   then removes the job directory.

`convert-server` (root, with the capabilities above) forks a process per connection, at most
`CONVERTER_MAX_JOBS` at once, each with the uid of its slot (2001, 2002…, created in the image,
no shell nor home). For each command:

1. the request is checked: a tool of the whitelist, bound to a fixed binary; a job directory that
   is a direct child of `/var/convert/jobs`, owned by the worker (uid 1000), not a link; a timeout
   of at most `CONVERTER_MAX_TIMEOUT` seconds;
2. the job directory is given to the uid of the slot (`lchown`, 0700): the job sees neither the
   other jobs nor `/var/convert/worker`;
3. the tool runs through `setpriv --reuid --regid --clear-groups --no-new-privs`, in its own
   process group, with a minimal environment (`PATH`, `LANG`, `HOME` and `TMPDIR` in the job) and
   no inherited descriptor; the group is killed on timeout or when the worker hangs up (its
   command was killed or timed out);
4. whatever happened, every process of the uid is killed (`kill(-1)` from that uid, until none is
   left: nothing outlives its job), everything that is neither a regular file nor a directory is
   removed from the job directory (symbolic links, fifos, sockets), the tree goes back to the
   worker (`lchown` 1000:1000), and only then the exit code is sent. The worker thus never follows
   a link planted by the tool: `out.jpg -> /proc/self/environ` would hand it its own secrets.

When the worker hangs up, the converter removes the job directory itself.

## Variables

| Variable                        | Default                         | Service                       |
|---------------------------------|---------------------------------|-------------------------------|
| `CONVERTER_URL`                 | `tcp://databox-converter:9000`  | databox workers; empty: the tools run in the worker, as before (tests, deployments without the converter) |
| `CONVERTER_MAX_JOBS`            | `4` (32 at most)                | databox-converter             |
| `CONVERTER_MAX_TIMEOUT`         | `3600` seconds                  | databox-converter             |
| `DATABOX_CONVERTER_CPU_LIMIT`   | `2`                             | databox-converter             |
| `DATABOX_CONVERTER_MEMORY_LIMIT`| `2G`                            | databox-converter             |
| `DATABOX_CONVERTER_PIDS_LIMIT`  | `1024`                          | databox-converter             |

The workers get `TMPDIR=/var/convert/worker` and the `convert` volume (on disk, never a tmpfs:
videos). The converter image carries the exiftool of `vendor/exiftool/exiftool`, copied from the
API image: the tag classes of phpexiftool are generated from that version (with the local exiftool
of the worker, which never reads an uploaded file).

## Limits

- **ImageMagick and Ghostscript still run in the workers**: they are libraries of the PHP process
  (Imagick, Ghostscript through `gslib`), and the rendition build (Twig context built from the
  entities, `AssetMetadataContainer`) would have to move with them. They are only bounded by the
  strict policy of the API and worker images (see [Renditions](./Renditions.mdx)).
- A file is handed to a single running job at a time: while a job runs, the files linked into it
  belong to its uid, which may change them (a source modified in place, a watermark of the cache).
  Two jobs given the same file at once still read it (the files of the worker are world-readable
  within the 0700 job directories), but the first one to end gives it back to the worker.
- The converter only sees the files of the command: a tool option naming another file of the worker
  (a font, a configuration file) fails.
- The output of a tool names the files by their path in the job (`SourceFile` of `exiftool -j`…),
  not by their path in the worker.
- stdin is not forwarded: no caller pipes data into these tools.
