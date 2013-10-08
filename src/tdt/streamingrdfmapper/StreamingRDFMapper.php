<?php
/**
 * Streaming RDF Mapper
 *
 * @author Pieter Colpaert
 */

namespace tdt\streamingrdfmapper;

class StreamingRDFMapper{
    
    private $mapping, $typeofmapping;

    /**
     * The constructor will check whether the mapping is alright by initializing the right mapping system
     * @param mapping is a string which contains the mapping file in a certain format
     * @param typeofmapping is e.g. RML, Vertere of OneonOne
     */
    public function __construct($mapping,$typeofmapping = ""){
        //todo: check input
        $this->mapping = $mapping;
        if($typeofmapping === ""){
            throw new Exception("type of mapping is empty");
        }
        $this->typeofmapping = $typeofmapping;
    }
    
    /**
     * Map a chunk towards triples.
     * @param chunk an array
     */
    public function map($chunk){
        //todo
        return array("subject" => "<>" , "predicate" => "<>" , "object" => "<>" );
    }

}