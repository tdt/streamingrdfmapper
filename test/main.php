#!/usr/bin/env php
<?php
include_once("../vendor/autoload.php");
//try{
    $mapper = new \tdt\streamingrdfmapper\StreamingRDFMapper('
@prefix : <http://vocab.mmlab.be/vertere/terms#> .
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix dc: <http://purl.org/dc/terms/> .
@prefix schema: <http://schema.org/> .
@prefix foaf: <http://xmlns.com/foaf/0.1/> .
@prefix wgs84_pos: <http://www.w3.org/2003/01/geo/wgs84_pos#> .
@prefix transit: <http://vocab.org/transit/terms/> .
@prefix virtrdf: <http://www.openlinksw.com/schemas/virtrdf#> .

<#> a :Spec
; :base_uri "http://data.irail.be/"
; :null_values ( " " "\n" "NULL" )
.

<#Route> a :Resource
; :type transit:Route
; :identity [
    :source_column "id"
    ]
; :attribute [
    :property dc:title;
    :source_column "name";
    :language "en";
    :datatype xsd:string ;
    :process ( :regex ) ;
    :regex_match "123";
    :regex_output "";
  ]
; :relationship [
    :property <http://foo.bar/has_park> ;
    :object_to <#Park>
] .

<#Park> a :Resource
; :type schema:Park
; :identity [
    :source_column "park";
    :base_uri "http://parks.be/"
   ]
.','Vertere');

$triples = $mapper->map(array("id" => "5", "name" => "test123", "park" => "Citadel"), true)->serialise("turtle");
print $triples;
    
//}
//catch(Exception $e){
    
    
//   echo $e->getMessage();
//}

