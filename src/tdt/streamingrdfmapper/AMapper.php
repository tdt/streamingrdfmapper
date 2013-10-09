<?php
/**
 * Streaming RDF Mapper abstract class
 *
 * @author Pieter Colpaert
 */

namespace tdt\streamingrdfmapper;

abstract class AMapper{
    private $mapping;
    public function __construct($mapping){
        //validate mapping first
        $this->validate($mapping);
        $this->mapping = $mapping;
    }

    abstract protected function validate($mapping);

    abstract public function map(&$chunk);

}


//$parser = ARC2::getTurtleParser();
