Taxonomies
==========


Retrieve All Taxonomies
-----------------------
The Taxonomies endpoint returns a collection containing objects for each of the
site's registered taxonomies.

	GET /taxonomies


### Response
The response is a collection document containing all registered taxonomies.


Retrieve a Taxonomy
-------------------

	GET /taxonomies/<taxonomy>

### Response
The response is a Taxonomy entity containing the requested Taxonomy, if available.


Retrieve Terms for a Taxonomy
-----------------------------

	GET /taxonomies/<taxonomy>/terms

### Response
The response is a collection of taxonomy terms for the specified Taxonomy, if
available.

Retrieve a Taxonomy Term
------------------------

	GET /taxonomies/<taxonomy>/terms/<id>

### Response
The response is a Taxonomy entity object containing the Taxonomy with the
requested ID, if available.
