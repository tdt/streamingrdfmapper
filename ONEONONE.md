# One on One

One on one is a very simple mapping language:
 * Every chunk is a type
 * Every chunk is defined by a URI which can be built from a value
 * Every value can be linked to the chunk URI with a predicate per key

An example of a mapping file:

```ini
[prefix]
transit=http://vocab.org/transit/terms/
[chunk]
type=transit:Stop
# "id" is a key and the user must make sure that all the chunks contain this key.
URI=http://stations.io/{id}
[keystopredicates]
id=dcterms:id
name=foaf:name
longitude=wgs84:long
longitude=wgs84:lat
```

It would map this input (e.g. in JSON):

```json
[
    "id" : "1",
    "name" : "A fake station",
    "longitude" : 3.14,
    "latitude" : 15.92
],
[
    "id" : "2",
    "name" : "A second fake station",
    "longitude" : 3.13,
    "latitude" : 51.1
]
```

towards the respective RDF representation.
