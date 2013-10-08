# Streaming RDF Mapper

This library maps PHP arrays towards RDF using different mapping languages:

## Vertere

The Vertere mapping language was the start of this repository. The code was reused from [mmmmmrob](https://github.com/mmmmmrob/Vertere).

You can find more documentation about Vertere in the [VERTERE.md](VERTERE.md) file.

## RML

See the publication of Anastasia Dimou, Miel Vander Sande, Pieter Colpaert on RML at ISWC 2013

## One on One

Map a column to properties: this is a very easy mapping language which doesn't offer a lot of flexibility

# Usage

## Installation

This repository is PSR-0 compliant and can be installed using composer:

```bash
composer install tdt/streamingrdfmapper
```

Not familiar with composer? Read about it [here](http://getcomposer.org)

## In code

```php
$mapping = file_get_contents("http://foo.bar/mapping/file.ttl");
$typeofmapping = "Vertere"; //other options: "RML", "OneonOne"

$mapper = new StreamingRDFMapper($mapping, $typeofmapping);
$data = foo\bar\getNextDataChunk(); //get data from somewhere: can be a csv file you've extracted, some data you've scraped or XML or JSON file you've flattened and put into an array
$triples = $mapper->map($data);
```

