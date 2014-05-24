Implementation Details
======================

Method Naming
-------------
By convention, all functions and methods use underscore_case, as per the
[WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/php/).


Routing
-------
The routing code is written in such a way to ensure that the input and output
are handled only by this code.

The majority of state is handled in the `serve_request()` method, which handles
both reading input and serializing the output to JSON. This is responsible for
translating HTTP to an internal representation suitable for dispatching.

The routing code also operates with global state, as it's intended to handle the
HTTP interactions, which is inherently global in PHP.

The `dispatch()` method handles matching the requested route with an endpoint.
This method also deals with global state for GET and POST parameters, however
this is not the optimal situation, as it requires reimplementation of the
routing code when embedding the API.

This could possibly be optimized by performing straight string matching for the
static portion of the URL. Symfony uses this internally in their routing,
although they don't allow arbitrary regular expressions for routes.


Parameters
----------
Endpoints take parameters as direct function arguments. This fits in with the
philosophy that the endpoints should match up with functions fairly directly.
This means that in order to write an endpoint, all you need to know is how to
write a function.

This uses the Reflection API to match provided parameters up with the function's
parameters in the correct order, and with default values as needed. The
performance of this API is anecdotally not a real consideration, however
benchmarks are yet to be taken in a rigorous manner.

Intentionally, it is not possible to get all supplied parameters. This is
regarded as a bad API smell, as each parameter should be fully documented for
consumers. It is possible to construct hacks around this using the
`json_dispatch_args` filter, but this is intentionally made to feel hacky.

Some parameters which give information about the context of the call. These are
prefixed with an underscore, and are *always* set. This ensures that a rogue
consumer can't pass in the internal name to override it and possibly cause a
security issue.
