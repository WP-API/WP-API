Philosophy
==========

Rules
-----

### Rule 1: Avoid writing global state

  For example, the routing code is responsible for handling all input and output,
  so direct calls from methods to functions such as `header()` or `echo` should
  be avoided completely.

### Rule 2: Endpoints must be embeddable

  Endpoints should be able to call each other without worrying about global state.
  This follows from Rule 1, in that global state must be avoided for this, however
  this also means that endpoints can be expected to be called internally.

### Rule 3: Explict is better than implicit

  You should never have to guess where a variable came from, or why a method was
  called. Routes should have a 1:1 mapping with endpoints and magic catch-all
  routes should be avoided. Parameters should always be documented as if the API
  was a closed (source) box.

### Rule 4: Reduce, reuse, recycle

  Reduce the complexity of clients and the server.

  Reuse code where possible.

  Recycle endpoints by building on the existing instead of reinventing.


Guidelines
----------

### Guideline 1: Endpoints should operate like regular functions

  By using the API, you're essentially using a form of Remote Procedure Call.
  Both remote and local calls should look essentially the same while operating
  within the convention of REST calls for the remote calls.
  
  Parameters to endpoints follow this rule, wherein remote parameters are
  matched up with defined parameters in the endpoint. More detailed structures
  are handled as entities, which have a clearly defined structure in
  the specification.
  
  This follows from Rules 2 and 3.

### Guideline 2: Endpoints should avoid assuming HTTP/JSON characteristics

  Although the API usually operates over HTTP using JSON, endpoints can safely
  assume that the serialization may take place using a different serializer
  (such as protobufs or bson) over a different transport (such as ZeroMQ).

  This doesn't mean that endpoints need to reinvent the wheel; HTTP status codes
  and header names are used since this is the normal state. However, values
  should never be encoded in endpoints for HTTP transport rules or JSON encoding
  rules.

  This follows from Rules 1 and 2.

### Guideline 3: Base responses around entities

  Being explicit with data formats means documenting everything. No one likes
  writing documentation constantly, so these data formats should be based around
  the concept of reusable entities.

  This simplifies the code and reduces the workload of documentation.

  This follows from Rules 3 and 4.
