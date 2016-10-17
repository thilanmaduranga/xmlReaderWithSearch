<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class PropertyListing extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library("pagination");
    }

    public function index() {
        $ar = array();


        // Setup defaults
        $results = array();
        $searchBarDefaults = array();

        $idsProcessed = array();

        // Process PS import
        $dataDirectory = 'psuiter/';
        $propertiesFileOutput = array();
        foreach (glob($dataDirectory . '*.xml') as $file) {
            try {
                $propertiesFileOutput[] = new SimpleXmlElement(file_get_contents($file));
            } catch (Exception $e) {
                
            }
        }

        // Flip array (give's most recent properties first)
        $propertiesFileOutput = array_reverse($propertiesFileOutput);


        // Populate results array
        foreach ($propertiesFileOutput as $properties) {
            foreach ($properties as $property) {
                $status = (string) $property->attributes()->status;
                $id = (string) $property->uniqueID;

                if (in_array($id, $idsProcessed) === true) {
                    continue;
                }
                array_push($idsProcessed, $id);

                // Only show 'current'
                if ($status === 'current' && isset($property->images)) {

                    if (!empty($this->input->get("searchOrigin"))) { // Check for search values
                        // Search form values
                        $propertyType = $this->input->get("propertyType");
                        $sellType = $this->input->get("sellType");
                        $minPriceLease = $this->input->get("minPriceLease");
                        $maxPriceLease = $this->input->get("maxPriceLease");
                        $minPriceBuy = $this->input->get("minPriceBuy");
                        $maxPriceBuy = $this->input->get("maxPriceBuy");
                        $minArea = (int) $this->input->get("minArea");
                        $maxArea = (int) $this->input->get("maxArea");
                        $searchText = $this->input->get("searchText");
                        $subrb=ucwords(strtolower($this->input->get("sub")));
                        $city=ucwords(strtolower($this->input->get("dist")));
                        $agent=ucwords(strtolower($this->input->get("agent")));

                        // Save search bar defaults
                        $searchBarDefaults['propertyType'] = $propertyType;
                        $searchBarDefaults['sellType'] = $sellType;
                        $searchBarDefaults['minPriceLease'] = $minPriceLease;
                        $searchBarDefaults['maxPriceLease'] = $maxPriceLease;
                        $searchBarDefaults['minPriceBuy'] = $minPriceBuy;
                        $searchBarDefaults['maxPriceBuy'] = $maxPriceBuy;
                        $searchBarDefaults['minArea'] = $minArea;
                        $searchBarDefaults['maxArea'] = $maxArea;
                        $searchBarDefaults['searchText'] = $searchText;

                        // Bastardise 'business category' into searchable values
                        $typeString = str_replace(' to ', ',', strtolower((string) $property->businessCategory->name));
                        $typeArray = explode(',', $typeString);

                        if (count($typeArray) !== 2) {
                            continue;
                        }


                        // Show office under commercial
                        if ($typeArray[0] === 'office') {
                            $typeArray[0] = 'commercial';
                        }


                        // Filter results
                        $currentPropertyType = (string) $typeArray[0];
                        $currentSellType = $typeArray[1];
                        $currentArea = (int) $property->buildingDetails->area;

                        $currentListingNumber = $id;

                        if (empty($searchText) === false) {
                            $aMatch = false;

                            if ($currentListingNumber == $searchText) {
                                goto parseResults; // goto statement is only used to test the code. I know some developers hate goto! ;)
                            }

                            $addressString = sprintf('%s %s, %s', $property->address->streetNumber, $property->address->street, $property->address->suburb);
                            if (stripos($addressString, $searchText) !== false) {
                                $aMatch = true;
                            }

                            if (stripos($property->description, $searchText) !== false) {
                                $aMatch = true;
                            }

                            if ($aMatch === false) {
                                continue;
                            }
                        }
                        
                        if(empty($subrb)===FALSE){
                            if(strcmp($subrb, $property->address->suburb)===0){
                                goto parseResults; // goto statement is only used to test the code. I know some developers hate goto! ;)
                            }
                        }
                        if(empty($city)===FALSE){
                            if(strcmp($city, $property->address->district)===0){
                                goto parseResults; // goto statement is only used to test the code. I know some developers hate goto! ;)
                            }
                        }
                        
                        if(empty($agent)===FALSE){
                            if(strcmp($agent, $property->listingAgent[0]->name)===0){
                                goto parseResults; // goto statement is only used to test the code. I know some developers hate goto! ;)
                            }
                            if(strcmp($agent, $property->listingAgent[1]->name)===0){
                                goto parseResults; // goto statement is only used to test the code. I know some developers hate goto! ;)
                            }
                        }

                        if ($currentPropertyType !== $propertyType) {
                            continue;
                        }

                        if ($currentSellType !== $sellType) {
                            continue;
                        }

                        if (!($currentArea >= $minArea)) {
                            
                            continue;
                        }

                        if ($maxArea !== 0) {
                            if (!($currentArea <= $maxArea)) {
                                continue;
                            }
                        }

                        // http://idiallo.com/blog/php-mysql-search-algorithm
                        // http://stackoverflow.com/questions/4912294/php-like-thing-similar-to-mysql-like-for-if-statement
                    }
                    parseResults:
                    if (array_search($id, $results) === false) { // Check for duplicates
                        $results[$id] = array();

                        // Get general data
                        $results[$id]['name'] = $property->businessCategory->name;
                        $results[$id]['price'] = $property->priceView;
                        $results[$id]['description'] = nl2br((string) $property->description);
                        $results[$id]['datetime'] = strtotime($property->attributes()->modTime);

                        // Get specific attributes
                        $results[$id]['area'] = (int) $property->buildingDetails->area;

                        // Get address lines
                        $results[$id]['streetNumber'] = $property->address->streetNumber;
                        $results[$id]['street'] = $property->address->street;
                        $results[$id]['suburb'] = $property->address->suburb;

                        $results[$id]['showSuburb'] = empty($property->address->suburb->attributes()->display) === false ? $property->address->suburb->attributes()->display : NULL;
                        $results[$id]['showAddress'] = empty($property->address->attributes()->display) === false ? $property->address->attributes()->display : NULL;

                        // Get image
                        $results[$id]['image'] = $dataDirectory . $property->images->img[0]->attributes()->file;
                        $results[$id]['images'] = $property->images;

                        $results[$id]['ref'] = $id;

                        $results[$id]["agent"] = $property->listingAgent->name;
                        
                    }
                }
            }
        }

        $searchBarDefaults = array();

        $idsProcessed = array();

        // Process PS import
        $dataDirectory = 'psuiter/';
        $propertiesFileOutput = array();
        foreach (glob($dataDirectory . '*.xml') as $file) {
            try {
                $propertiesFileOutput[] = new SimpleXmlElement(file_get_contents($file));
            } catch (Exception $e) {
                
            }
        }

        // Flip array (give's most recent properties first)
        $propertiesFileOutput = array_reverse($propertiesFileOutput);
        $resu = array();
        $cnt = 0;
        // Populate results array
        foreach ($propertiesFileOutput as $properties) {

            foreach ($properties as $property) {
                $status = (string) $property->attributes()->status;
                $id = (string) $property->uniqueID;


                if (in_array($id, $idsProcessed) === true) {
                    continue;
                }
                array_push($idsProcessed, $id);

                // Only show 'current'
                if ($status === 'current' && isset($property->images)) {
                    $cnt = $cnt + 1;
                    if ($cnt <= 6) {
                        if (array_search($id, $resu) === false) { // Check for duplicates
                            $resu[$id] = array();

                            // Get general data
                            $resu[$id]['name'] = $property->businessCategory->name;
                            $resu[$id]['price'] = $property->priceView;
                            $resu[$id]['description'] = nl2br((string) $property->description);
                            $resu[$id]['link'] = 'individual.php?s=' . $id;
                            // $results[$id]['datetime'] = strtotime($property->attributes()->modTime);
                            // Get specific attributes
                            $resu[$id]['area'] = (int) $property->buildingDetails->area;

                            // Get address lines
                            $resu[$id]['streetNumber'] = $property->address->streetNumber;
                            $resu[$id]['street'] = $property->address->street;
                            $resu[$id]['suburb'] = $property->address->suburb;

                            $resu[$id]['showSuburb'] = empty($property->address->suburb->attributes()->display) === false ? $property->address->suburb->attributes()->display : NULL;
                            $resu[$id]['showAddress'] = empty($property->address->attributes()->display) === false ? $property->address->attributes()->display : NULL;

                            // Get image
                            $resu[$id]['image'] = $dataDirectory . $property->images->img[0]->attributes()->file;

                            $resu[$id]['ref'] = $id;

                            $resu[$id]["agent"] = $property->listingAgent->name;
                        }
                    }
                }
            }
        }
        $pgcnt=0;
        $frmpage=0;
        // codeigniter pagination
        $config = array();
        $config["base_url"] = current_full_url();
        $config["total_rows"] = count($results);
        $config["per_page"] = 2;
        $config["uri_segment"] = 2;
        $page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 1;
        
        $pgcnt=$page;
        if($page===1){
            $page=0;
            $pgcnt=2;
        }
        else{
             $pgcnt=$pgcnt+2;
        }
        
        
        
        $ar["results"] =array_slice($results,$page,$pgcnt);

        $this->pagination->initialize($config);
        
        // collect all results
        $ar["links"] = $this->pagination->create_links();

        $ar["title"] = "Property Listings";
        $ar["activeSate"] = "propertyse";
        
        $ar["res"] = $resu;
        // send to the views
        $this->load->view('mainIncludes/head', $ar);
        $this->load->view('mainIncludes/header');
        $this->load->view('PropertyListing');
        $this->load->view('mainIncludes/footer');
        $this->load->view('mainIncludes/jsplugins');
    }

    

}
