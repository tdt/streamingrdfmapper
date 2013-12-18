# Streaming RDF Mapper

This library maps PHP arrays towards RDF using different mapping languages:

## Vertere

The Vertere mapping language was the start of this repository. The code was reused from [mmmmmrob](https://github.com/mmmmmrob/Vertere).

You can find more documentation about Vertere in the [VERTERE.md](VERTERE.md) file. It is at this moment the only supported language.

## RML

Will be the future language of this repository. See the publication of Anastasia Dimou, Miel Vander Sande, Pieter Colpaert on RML at ISWC 2013

You can find more documentation about Vertere in the [RML.md](RML.md) file.

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
$typeofmapping = "Vertere";
$mapper = new StreamingRDFMapper($mapping, $typeofmapping);
$data = foo\bar\getNextDataChunk(); //get data from somewhere: can be a csv file you've extracted, some data you've scraped or XML or JSON file you've flattened and put into an array
$getEasyRDFGraph = true;
$triplesEasyRDFGraph = $mapper->map($data, $getEasyRDFGraph);
$triplesArray = $mapper->map($data, !$getEasyRDFGraph);
//print ntriples through easy graph (some overhead, but really good library*)
print $triplesEasyRDFGraph->serialize("ntriples");
//print ntriples through array (faster)
foreach($triplesArray as $triple){
  print implode(" ", $triple);
  print " . \n";
}

```

You can also set a standard base uri for the mapper by after creating an instance doing this:

```php
$mapper->setBaseUri("http://data.iRail.be/");
```
* The EasyRDF library 

