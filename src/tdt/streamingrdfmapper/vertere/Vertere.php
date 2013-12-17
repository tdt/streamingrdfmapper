<?php
/**
 * The Vertere mapping language parser
 *
 * @author until 2012: Rob Styles
 * @author starting 2012: Miel Vander Sande
 * @author starting 2013: Pieter Colpaert
 */

namespace tdt\streamingrdfmapper\vertere;
use \EasyRdf_Parser_Turtle;
use \EasyRdf_Graph;
use \Exception;

class Vertere extends \tdt\streamingrdfmapper\AMapper {

    private $resources, $base_uri, $lookups = array(), $null_values = array();

    private $ns = array(
        //"vertere" => "http://example.com/schema/data_conversion#",
        "vertere" => "http://vocab.mmlab.be/vertere/terms#",
	"rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
        "rdf"=> "http://www.w3.org/1999/02/22-rdf-syntax-ns#",

    );

    private $mapping;

    /**
     * Parses a mapping file and stores the right parameters in this class
     */
    protected function validate(&$mapping) {
        //parsing the mapping file seems like a first good step
        $this->mapping = new EasyRdf_Graph("#");
        $parser = new EasyRdf_Parser_Turtle();
        $parser->parse($this->mapping, $mapping, "turtle","http://foo.bar");
        // Find resource specs: check for resource triple values: this->resources is an array of Resources
        $this->resources = $this->mapping->allOfType("<" . $this->ns["vertere"] . "Resource>");
        if(empty($this->resources)) {
            throw new Exception("Unable to find any resource specs to work from");
        }

        //base_uri is the URI to be used when an empty prefix is used
        $base_uri_literal = $this->mapping->getLiteral("<http://foo.bar#>","<" . $this->ns["vertere"] . "base_uri>");
        if(!is_object($base_uri_literal)){
            if(!isset($this->baseUri)){
                throw new Exception("No base uri is set in the #Spec of the Vertere Mapping File");
            }else{
                $this->base_uri = $this->baseUri;
            }
        }else{
            $this->base_uri = $base_uri_literal->getValue();
        }

        // :null_values is a list of strings that indicate NULL in the source data
        $null_value_list = $this->mapping->getResource("<http://foo.bar#>", '<' . $this->ns["vertere"] . 'null_values>');

        if ($null_value_list && $null_value_list instanceof \EasyRdf_Collection) { //!! If this rdf:List is not well built, this is going to give serious problems
            while($null_value_list->valid()){
                array_push($this->null_values, $null_value_list->current()->getValue());
                $null_value_list->next();
            }
        } else {
            array_push($this->null_values, "");
        }
    }

    /**
     * Get a value from a record - This is needed to allow automatic trimming of the value and to lower the array number with 1, as vertere starts to count from 1
     */
    public function getRecordValue(&$record, $key) {

        //if the key doesn't exist in the record, lower the number with one and try again (we start counting from 1 in vertere)
        if (!array_key_exists($key, $record) && is_numeric($key) && array_key_exists($key -1, $record)){
            $key --;
        } else if (!array_key_exists($key, $record)){
            // Be forgiving! 
            // throw new Exception("Source column value is not valid: the value '$key' could not be found");
            return "";
        }

        return trim($record[$key]);
    }

    /**
     * Maps a chunk towards an EasyRDF graph
     */
    public function map(&$chunk){
        //builds all the uris that can be built for this record according to the mapping file

        $uris = $this->createUris($chunk);

        $graph = array();
        $this->addDefaultTypes($graph, $uris);

        $this->createRelationships($graph, $uris, $chunk);

        $this->createAttributes($graph, $uris, $chunk);

        return $graph;
    }

    /**
     * Adds all types to the graph according to the mapping file
     */
    private function addDefaultTypes(&$graph, &$uris) {
        foreach ($this->resources as $resource) {
            $types = $this->mapping->allResources($resource, "<" . $this->ns["vertere"] . "type>");
            foreach ($types as $type) {
                if (!empty($type) && isset($uris[$resource->getUri()])) {
                    $graph[] = array(
                        "subject" => "<" . $uris[$resource->getUri()] . ">",
                        "predicate" => "<" . $this->ns["rdf"] . "type"  . ">",
                        "object" => "<" . $type->getUri()  . ">"
                    );

                }
            }
        }
    }

    /**
     * Create attributes for all the the resources in the graph
     */
    private function createAttributes(&$graph, &$uris, &$record) {
        foreach ($this->resources as $resource) {
            $attributes = $this->mapping->allResources($resource, "<" . $this->ns["vertere"] . "attribute>");
            foreach ($attributes as $attribute) {
                $this->createAttribute($graph, $uris, $record, $resource, $attribute);
            }
        }
    }

    private function createAttribute(&$graph, &$uris, &$record, &$resource, $attribute) {
        if (!isset($uris[$resource->getUri()])) {
            return;
        }
        $subject = $uris[$resource->getUri()];
        $property = $this->mapping->getResource($attribute, "<" . $this->ns["vertere"] . "property>");
        $language = $this->mapping->getLiteral($attribute, "<" . $this->ns["vertere"] . "language>");

        if( $language){
            $language = $language->getValue(); //TODO: document this parameter?
        }
        $datatype = $this->mapping->getResource($attribute, "<" . $this->ns["vertere"] . "datatype>");

        $value = $this->mapping->getLiteral($attribute, "<" . $this->ns["vertere"] . "value>");
        $source_column = $this->mapping->getLiteral($attribute, "<" . $this->ns["vertere"] . "source_column>");
        $source_columns = $this->mapping->getResource($attribute, "<" . $this->ns["vertere"] . "source_columns>");

        if ($value) {
            $source_value = $value->getValue();
        } else if ($source_column) {
            $source_value = $this->getRecordValue($record, $source_column->getValue());
        } else if ($source_columns) {
            $source_columns_collection = $source_columns;
            $source_columns = array();
            if ($source_columns_collection && $source_columns_collection instanceof \EasyRdf_Collection) { //!! If this rdf:List is not well built, this is going to give serious problems
                while($source_columns_collection->valid()){
                    array_push($source_columns, $source_columns_collection->current()->getValue());
                    $source_columns_collection->next();
                }
            }

            $glue = $this->mapping->getLiteral($attribute, "<" . $this->ns["vertere"] . "source_column_glue>");
            $filter = $this->mapping->getLiteral($attribute, "<" . $this->ns["vertere"] . "source_column_filter>");
            if (!isset($filter)) {
                // default: accept anything
                $filter = "//";
            }
            $source_values = array();
            foreach ($source_columns as $source_column) {
                $source_column = $source_column;
                $value = $this->getRecordValue($record, $source_column);

                if (preg_match($filter, $value) != 0 && !in_array($value, $this->null_values)) {
                    $source_values[] = $value;
                }
            }
            $source_value = implode($glue, $source_values);
        } else {
            return;
        }
        $lookup = $this->mapping->getResource($attribute, "<" . $this->ns["vertere"] . "lookup>");
        if ($lookup != null) {
            $lookup_value = $this->lookup($record, $lookup, $source_value);
            
            if ($lookup_value != null && $lookup_value['type'] == 'uri') {
                $graph[] = array(
                    "subject" => "<" . $subject . ">",
                    "predicate" => "<" . $property . ">",
                    "object" => "\"" . str_replace("\"", "\\\"", $this->escapeBackslashes($lookup_value['value'])) . "\""
                );
                return;
            }
            else {
                $source_value = $lookup_value['value'];
            }
        }

        if (empty($source_value)) {
            return;
        }

        $source_value = $this->process($attribute, $source_value);
        $suffix = "";
        if (isset($datatype)){
            $suffix = "^^<$datatype>";
        }

        if (isset($language)){
            $suffix .= "@$language";
        }
        
        $source_value = $this->escapeBackslashes($source_value);

        $graph[] = array(
            "subject" => "<" . $subject . ">",
            "predicate" => "<" . $property . ">",
            "object" => "\"" . str_replace("\"", "\\\"",$source_value) . "\"$suffix"
        );
    }

    private function escapeBackslashes($str) {
         return preg_replace('/\\\\([^uUtnr"\\\\])/','\\\\\\\\$1', $str);
    }

    private function createRelationships(&$graph, &$uris, &$record) {
        foreach ($this->resources as $resource) {
            $relationships = $this->mapping->allResources($resource, "<" . $this->ns["vertere"] . "relationship>");
            foreach ($relationships as $relationship) {
                $this->createRelationship($graph, $uris, $resource, $relationship, $record);
            }
        }
    }

    private function createRelationship(&$graph, &$uris, &$resource, &$relationship, &$record) {
        $subject = null;
        if (array_key_exists($resource->getUri(), $uris))
            $subject = $uris[$resource->getUri()];

        $property = $this->mapping->getResource($relationship, "<" . $this->ns["vertere"] . "property>");

        $object_from = $this->mapping->getResource($relationship, "<" . $this->ns["vertere"] . "object_from>");
        $identity = $this->mapping->getResource($relationship, "<" . $this->ns["vertere"] . "identity>");
        $object = $this->mapping->getResource($relationship, "<" . $this->ns["vertere"] . "object>");
        $new_subject = $this->mapping->getResource($relationship, "<" . $this->ns["vertere"] . "subject>");

        if ($object_from) {
            //Prevents PHP warning on key not being present
            if (isset($uris[$object_from->getUri()]))
                $object = $uris[$object_from->getUri()];
        } else if ($identity) {

            $source_column = $this->mapping->getLiteral($identity, "<" . $this->ns["vertere"] . "source_column>");
            $column = $source_column->getValue();
            $source_value = $this->getRecordValue($record, $column);

            if (empty($source_value)) {
                return;
            }

            //Check for lookups
            $lookup = $this->mapping->getResource($identity, "<" . $this->ns["vertere"] . "lookup>");
            if ($lookup) {
                $lookup_value = $this->lookup($record, $lookup->getUri(), $source_value);
                if ($lookup_value != null && $lookup_value['type'] == 'uri') {
                    $uris[$resource->getUri()] = $lookup_value['value'];
                    return;
                } else {
                    $source_value = $lookup_value['value'];
                }
            }

            $base_uri = $this->mapping->getLiteral($identity, "<" . $this->ns["vertere"] . "base_uri>");
            if (! $base_uri->getValue()) {
                $base_uri = $this->base_uri;
            }
            $source_value = $this->process($identity, $source_value);
            $object = "${base_uri}${source_value}";
        } else if ($new_subject) {
            $object = $subject;
            $subject = $new_subject;
        }

        if ($subject && $property && $object) {
            //$graph->addResource($subject, $property, $object);

            $graph[] = array(
                "subject" => "<" . $subject . ">",
                "predicate" => "<" . $property . ">",
                "object" => "<" . $object . ">"
            );
        } else {
            return;
        }
    }

    private function createUris(&$record) {
        $uris = array();
        foreach ($this->resources as $resource) {
            if (!isset($uris[$resource->getUri()])) {
                $this->createUri($record, $uris, $resource);
            }
        }
        return $uris;
    }

    private function create_template_uri(&$record, &$template, &$vars) {
        $var_arr = array();
        foreach ($vars as $var) {
            $name = $this->mapping->getLiteral($var, "<" . $this->ns["vertere"] . "variable>");
            $source_column = $this->mapping->getLiteral($var, "<" . $this->ns["vertere"] . "source_column>");
            $value = $this->getRecordValue($record, $source_column);
            $var_arr[$name] = $value;
        }

        $processor = new \Guzzle\Parser\UriTemplate\UriTemplate();
        return $processor->expand($template, $var_arr);
    }

    private function createUri(&$record, &$uris, &$resource, &$identity = null) {
        if (!$identity) {
            $identity = $this->mapping->getResource($resource, "<" . $this->ns["vertere"] . "identity>");
        }
        $source_column = $this->mapping->getLiteral($identity, "<" . $this->ns["vertere"] . "source_column>");
        //support for multiple source columns
        $source_columns = $this->mapping->getResource($identity, "<" . $this->ns["vertere"] . "source_columns>");
        $source_resource = $this->mapping->getResource($identity, "<" . $this->ns["vertere"] . "source_resource>");

        //Support for URI templates
        $template = $this->mapping->getLiteral($identity, "<" . $this->ns["vertere"] . "template>");
        if($template){
            $template = $template->getValue();
            $varsList = $this->mapping->getResource($identity, "<" . $this->ns["vertere"] . "template_vars>");
            $vars = array();
            if ($varsList && $varsList instanceof \EasyRdf_Collection) {
                while($varsList->valid()){
                    array_push($vars, $varsList->current()->getValue());
                    $varsList->next();
                }
            }
            $uri = $this->create_template_uri($record, $template, $vars);
            $uris[$resource] = $uri;
            return;
        } else if ($source_column) {
            $source_value = $this->getRecordValue($record, $source_column->getValue());
        } else if ($source_columns) {
            $glue = $this->mapping->getLiteral($identity, "<" . $this->ns["vertere"] . "source_column_glue>");
            $source_values = array();
            
            if($source_columns && $source_columns instanceof \EasyRdf_Collection) {
                
                while($source_columns->valid()){
                    $source_column = $source_columns->current();

                    $source_column = $source_column->getValue();
                    $key = is_numeric($source_column) ? $source_column - 1 : $source_column;

                    if (array_key_exists($key, $record)) {
                        if (!in_array($record[$key], $this->null_values)) {
                            $source_values[] = $this->getRecordValue($record, $source_column);
                        } else {
                            $source_values = array();
                            echo "streamingrdfmapper: WARNING: skipping chunk because of empty source_columns\n";
                            break;
                        }
                    }
                    $source_columns->next();
                }
            }
            	
            	
            // Rewind the collection.
            $source_columns->rewind();
            
            $source_value = implode('', $source_values);
            if (!empty($source_value)) {
                $source_value = implode($glue, $source_values);
            }
            
        } else if ($source_resource) {
            if (!isset($uris[$source_resource->getUri()])) {
                $this->createUri($record, $uris, $source_resource->getUri());
            }
            //Prevents PHP warning on key not being present
            if (isset($uris[$source_resource->getUri()]))
                $source_value = $uris[$source_resource->getUri()];
        } else {
            return;
        }

        //Check for lookups
        $lookup = $this->mapping->getResource($identity, "<" . $this->ns["vertere"] . "lookup>");
        if ($lookup != null) {
            $lookup_value = $this->lookup($record, $lookup, $source_value);
            if ($lookup_value != null && $lookup_value['type'] == 'uri') {
                $uris[$resource] = $lookup_value['value'];
                return;
            } else {
                $source_value = $lookup_value['value'];
            }
        }

        //Decide on base_uri
        $base_uri = $this->mapping->getLiteral($identity, "<" . $this->ns["vertere"] . "base_uri>");
        if ($base_uri === null) {
            $base_uri = $this->base_uri;
        }

        //Decide if the resource should be nested (overrides the base_uri)
        $nest_under = $this->mapping->getResource($identity, "<" . $this->ns["vertere"] . "nest_under>");
        if ($nest_under != null) {
            if (!isset($uris[$nest_under])) {
                $this->createUri($record, $uris, $nest_under);
            }
            $base_uri = $uris[$nest_under];
            if (!preg_match('%[/#]$%', $base_uri)) {
                $base_uri .= '/';
            }
        }

        $container = $this->mapping->getLiteral($identity, "<" . $this->ns["vertere"] . "container>");
        if (!empty($container) && !preg_match('%[/#]$%', $container)) {
            $container .= '/';
        }

        //Prevents PHP warning on key not being present
        if (!isset($source_value))
            $source_value = null;

        $source_value = $this->process($identity, $source_value);

        if (!empty($source_value)) {
            $uri = "${base_uri}${container}${source_value}";
            $uris[$resource->getUri()] = $uri;
        } else {
            $identity = $this->mapping->getResource($resource, "<" . $this->ns["vertere"] . "alternative_identity>");
            if ($identity) {
                $this->createUri($record, $uris, $resource, $identity);
            }
        }
    }

    public function process(&$resource, &$value) {
        $processes = $this->mapping->getResource($resource, "<" . $this->ns["vertere"] . "process>");
        if ($processes != null) {
            $process_steps_list = $processes;
            $process_steps = array();
            if ($process_steps_list && $process_steps_list instanceof \EasyRdf_Collection) {
                while($process_steps_list->valid()){
                    array_push($process_steps, $process_steps_list->current());
                    $process_steps_list->next();
                }
                $process_steps_list->rewind();
            }

            foreach ($process_steps as $step) {
                $function = str_replace($this->ns["vertere"], "", $step->getUri());
                switch ($function) {
                    case 'normalise':
                        //$value = strtolower(str_replace(' ', '_', trim($value)));
                        // Swap out Non "Letters" with a _
                        $value = preg_replace('/[^\\pL\d]+/u', '_', $value);

                        // Trim out extra -'s
                        $value = trim($value, '-');

                        // Convert letters that we have left to the closest ASCII representation
                        $value = iconv('utf-8', 'us-ascii//TRANSLIT', $value);

                        // Make text lowercase
                        $value = strtolower($value);

                        // Strip out anything we haven't been able to convert
                        $value = preg_replace('/[^-\w]+/', '', $value);

                        break;

                    case 'trim_quotes':
                        $value = trim($value, '"');
                        break;

                    case 'flatten_utf8':
                        $value = preg_replace('/[^-\w]+/', '', iconv('UTF-8', 'ascii//TRANSLIT', $value));
                        break;

                    case 'title_case':
                        $value = ucwords($value);
                        break;

                    case 'url_encode':
                        $value = urlencode($value);
                        $value = str_replace("+", "%20", $value);
                        break;

                        /**
                         * create_url wil check whether the argument is not a url yet.
                         * If it is, it will keep the url as is.
                         * If it isn't, it will prepend the begining of the url, and it will url encode the value
                         */
                    case 'create_url':
                        $regex_output = $this->mapping->getLiteral($resource, "<" . $this->ns["vertere"] . "url>");
                        $regex_pattern = "/^(?!http.+)/";
                        if (preg_match($regex_pattern, $value)) {
                            $value = urlencode($value);
                            $value = str_replace("+", "%20", $value);
                            $value = preg_replace("${regex_pattern}", $regex_output, $value);
                        }
                        break;

                    case 'regex':
                        $regex_pattern = $this->mapping->getLiteral($resource, "<" . $this->ns["vertere"] . "regex_match>");
                        $regex_pattern = $regex_pattern->getValue();
                        foreach (array('%', '/', '@', '!', '^', ',', '.', '-') as $candidate_delimeter) {
                            if (strpos($candidate_delimeter, $regex_pattern) === false) {
                                $delimeter = $candidate_delimeter;
                                break;
                            }
                        }
                        //MVS: Added this as a correction, not sure what above foreach does but breaking the regex
                        $delimeter = "/";
                        $regex_output = $this->mapping->getLiteral($resource, "<" . $this->ns["vertere"] . "regex_output>");
                        $value = preg_replace("${delimeter}${regex_pattern}${delimeter}", $regex_output, $value);
                        break;

                    case 'round':
                        $value = round($value);
                        break;

                    case 'substr':
                        $substr_start = $this->mapping->getLiteral($resource, "<" . $this->ns["vertere"] . "substr_start>");
                        $substr_length = $this->mapping->getLiteral($resource, "<" . $this->ns["vertere"] . "substr_length>");
                        $value = substr($value, $substr_start, $substr_length);
                        break;

                    default:
                        //When no built in function matches, a custom process function in called
                        //Made Conversion a little more flexible
                        if (method_exists("\\tdt\\streamingrdfmapper\\Conversions", $function)) {
                            //PC: TODO: change this so that process doesn't contain any function anymore, but reads everything from the Conversions class
                            $value = \tdt\streamingrdfmapper\Conversions::$function($value);
                        }
                        else {
                            throw new Exception("Unknown process requested: $function\n");
                        }
                }
            }
        }
        return $value;
    }

    public function lookup( &$record, $lookup, &$key) {
        if ($this->mapping->getLiteral($lookup, "<" . $this->ns["vertere"] . "lookup_entry>")) {
            return $this->lookup_config_entries($record, $lookup, $key);
        } else if ($this->mapping->getLiteral($lookup, "<" . $this->ns["vertere"] . "lookup_csv_file>")) {
            return $this->lookup_csv_file($lookup, $key);
        }
    }

    function lookup_config_entries(&$record, &$lookup, &$key) {
        if (!isset($this->lookups[$lookup])) {
            $entries = $this->mapping->allResources($lookup, "<" . $this->ns["vertere"] . "lookup_entry>");
            if (empty($entries)) {
                throw new Exception("Lookup ${lookup} had no lookup entries");
            }
            foreach ($entries as $entry) {
                $entry = $entry->getUri();
                //Accept lookups with several keys mapped to a single value
                $lookup_keys = $this->mapping->allLiterals($entry, "<" . $this->ns["vertere"] . "lookup_key>");
                $lookup_column = $this->mapping->getLiteral($entry, "<" . $this->ns["vertere"] . "lookup_column>");
                foreach ($lookup_keys as $lookup_key_array) {
                    $lookup_key = $lookup_key_array->getValue();
                    if (isset($this->lookups[$lookup][$lookup_key])) {
                        throw new Exception("Lookup <${lookup}> contained a duplicate key");
                    }
                    $lookup_values = $this->mapping->allLiterals($entry, "<" . $this->ns["vertere"] . "lookup_value>");
                    if (count($lookup_values) > 1) {
                        throw new Exception("Lookup ${lookup} has an entry ${entry['value']} that does not have exactly one lookup value assigned.");
                    }
                    if ($lookup_column){
                        $this->lookups[$lookup][$lookup_key]['value'] = $lookup_column[0]->getValue();
                        $this->lookups[$lookup][$lookup_key]['type'] = true;
                    }
                    elseif ($lookup_values[0]){
                        $this->lookups[$lookup][$lookup_key]['value'] = $lookup_values[0]->getValue();
                        $this->lookups[$lookup][$lookup_key]['type'] = false;
                    }
                }
            }
        }


        if (isset($this->lookups[$lookup]) && isset($this->lookups[$lookup][$key])) {
            if ($this->lookups[$lookup][$key]['type']){
                $column_value['value'] = $this->getRecordValue($record, $this->lookups[$lookup][$key]['value']);
                return $column_value;
            }
            elseif(!$this->lookups[$lookup][$key]['type'])
                return $this->lookups[$lookup][$key]['value'];
        }
        else {
            $return['value'] = $key;
            $return['type'] = false;
            return $return;
        }
    }

    function lookup_csv_file(&$lookup, &$key) {

        if (isset($this->lookups[$lookup]['keys']) AND isset($this->lookups[$lookup]['keys'][$key])) {
            return $this->lookups[$lookup]['keys'][$key];
        }

        $filename = $this->mapping->getLiteral($lookup, "<" . $this->ns["vertere"] . "lookup_csv_file>");
        $key_column = $this->mapping->getLiteral($lookup, "<" . $this->ns["vertere"] . "lookup_key_column>");
        $value_column = $this->mapping->getLiteral($lookup, "<" . $this->ns["vertere"] . "lookup_value_column>");
        //retain file handle
        if (!isset($this->lookups[$lookup]['filehandle'])) {
            $this->lookups[$lookup]['filehandle'] = fopen($filename, 'r');
        }
        while ($row = fgetcsv($this->lookups[$lookup]['filehandle'])) {
            if ($row[$key_column] == $key) {
                $value = $row[$value_column];
                $this->lookups[$lookup]['keys'][$key] = $value;
                return $value;
            }
        }
        return false;
    }

}
