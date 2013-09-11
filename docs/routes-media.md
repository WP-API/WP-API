Media
=====

Get Attachments
---------------
The Attachments endpoint returns an Attachment collection containing a subset of
the site's attachments.

This endpoint is an extended version of the Post retrieval endpoint.

	GET /media

### Input
#### `fields`
...

### Response
The response is an Attachment entity containing the requested Attachment if
available.


Create an Attachment
--------------------
The Create Attachment endpoint is used to create the raw data for an attachment.
This is a binary object (blob), such as image data or a video.

	POST /media

### Input
The attachment creation endpoint can accept data in two forms.

The primary input method accepts raw data POSTed with the corresponding content
type set via the `Content-Type` HTTP header. This is the preferred submission
method.

The secondary input method accepts data POSTed via `multipart/form-data`, as per
[RFC 2388][]. The uploaded file should be submitted with the name field set to
"file", and the filename field set to the relevant filename for the file.

In addition, a `Content-MD5` header can be set with the MD5 hash of the file, to
enable the server to check for consistency errors. If the supplied hash does not
match the hash calculated on the server, a 412 Precondition Failed header will
be issued.

[RFC 2388]: http://tools.ietf.org/html/rfc2388

### Response
On a successful creation, a 201 Created status is given, indicating that the
attachment has been created. The attachment is available canonically from the
URL specified in the Location header.

The new Attachment entity is also returned in the body for convienience.