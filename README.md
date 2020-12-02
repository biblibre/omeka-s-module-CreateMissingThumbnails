# Create Missing Thumbnails

This Omeka S module adds a background job that create missing thumbnails. This
is mostly useful if you want to import a lot of items quickly (so with
thumbnail creation turned off, because it takes a lot of time), and then create
the thumbnails in a 2nd phase, once all items are created.

Thumbnails are created for every media that meet the following conditions:
- The original file is stored (`media.hasOriginal = 1`)
- It has no thumbnails (`media.hasThumbnails = 0`)
- It is an image, a video, or a PDF (`media.mediaType` starts with `image/` or `video/`, or is `application/pdf`)

## License

GPL 3.0 or later
