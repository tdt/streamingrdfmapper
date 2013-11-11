<?php
/**
 * Streaming RDF Mapper
 * This class hides the mapper logic from the user of the library.
 * It uses a strategy design pattern to choose the right mapper according to the type of mapping which has been given
 *
 * For usage, see README.md
 *
 * @author Pieter Colpaert <pieter.colpaert aŧ UGent.be>
 */

namespace tdt\streamingrdfmapper;

class StreamingRDFMapper{
    
    //Strategy design pattern
    private $mapper;
    private $mappertypes = array("OneOnOne" => "\\tdt\\streamingrdfmapper\\oneonone\\OneOnOne",
                                 "Vertere" => "\\tdt\\streamingrdfmapper\\vertere\\Vertere",
                                 "RML" => "\\tdt\\streamingrdfmapper\\rml\\RML");

    /**
     * The constructor will check whether the mapping is alright by initializing the right mapping system
     * @param mapping is a string which contains the mapping file in a certain format
     * @param typeofmapping is e.g. RML, Vertere of OneonOne
     * @throws several exceptions depending on the type of mapping
     */
    public function __construct($mapping,$typeofmapping = ""){
        if($typeofmapping === ""){
            throw new Exception("type of mapping is empty");
        }
        if(in_array($typeofmapping,array_keys($this->mappertypes))){
            $classname = $this->mappertypes[$typeofmapping];
            $this->mapper = new $classname($mapping);
        }else{
            throw new Exception("Mapper does not exist: " . $typeofmapping);
        }
    }

    /**
     * This function sets the base Uri for the mapping language
     * @param baseUri a uri to be added at the beginning of every final resource with a relative path
     */
    public function setBaseUri($baseUri){
        $this->mapper->setBaseUri($baseUri);
    }
    
    
    /**
     * Map a chunk towards triples.
     * @param chunk an array
     * @param easyrdf a boolean whether or not an EasyRDF class should be returned. Defaults to false.
     * @return triples in an easyRDF class or in a simple array, depending on the second arguments.
     */
    public function map($chunk, $easyrdf = false){
        if($easyrdf){
            return $this->mapper->mapToEasyRDF($chunk);
        }else{
            return $this->mapper->map($chunk);
        }
    }

}