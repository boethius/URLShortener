<?php

/**
 *  Make sure it's easy to debug.
 **/

ini_set('log_errors',1);
error_reporting(E_ALL);


/**
 *  Shortens URLs by creating a random key and storing the in a skip on the
 *  file system. If the requirement constraints are lifted, the keys could be
 *  stored in a database.
 *
 *  Features:
 *   - generate alpha numerical keys, at least 36^6-35^5 = 2'116'316'160 entries
 *   - key length is variable, but must be at least six
 *   - values are store on disk in a skip list; two-char folder, then in a
 *     filename, then in the file itself. 
 *     skip lists are in O(n) space with worst case O(n log n), but searches are
 *     in O(log n) time.
 *     NOTE: certain filesystems have a maximum number of files and folders, 
 *           this is why the folders and files are limited to two chars.
 *   - can generate keys without locking
 *   - checks if the passed url is valid
 *
 *  @author: Sebastian Boëthius
 *  @email: sebastian@boethius.ch
 *  @date: 2015-05-07
 **/

class URLShortener {
    const KEYLENGTH = 6; // minimum 6
    const ALPHABET = "abcdefghijklmnopqrstuvwxyz0123456789"; // keep case insensitive for different filesystems
    const STORAGE = "keys/"; // place where the keys are stored
    const CHMOD = 0775; // makes the folder group writable, in case php is executed with a different user
    const DEBUG = false; // for debugging
    public $alpha = array(); // stores the alphabet array after initalization


    /**
     *  Constructor.
     *
     *  Initiates the alphabet and handles the requests.
     *
     **/
    function __construct(){
        // Init alphabet array.
        $this->alpha = str_split(self::ALPHABET);

        // Return the key from the url as json for AJAX interpretation
        if(isset($_POST['url'])){
            header('Content-Type: application/json');
            echo $this->createURL(html_entity_decode($_POST['url']));
        }
        // If Apache Mod Rewrite works, send the user to resolved url
        else if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']!="/") {
            $key = substr($_SERVER['REQUEST_URI'],1);
            $pkg = $this->getURL($key);
            // if url is resolved, send the user there
            if($pkg->getMessage() == "found") $url = "http".$pkg->getData();
            // otherwise, send the user back to the domain
            else $url = "http://".$_SERVER["SERVER_NAME"];
            header('Location: '.$url);
        }
        // If we don't have Apache Mod Rewrite, use the normal get variables
        else if(isset($_GET['key'])){
            $pkg = $this->getURL($_GET['key']);
            if($pkg->getMessage() == "found") $url = "http".$pkg->getData();
            else $url = "http://".$_SERVER["SERVER_NAME"];
            header('Location: '.$url);
        }
        // Otherwise just show the normal page.
        else{
            include('home.php');
        }
    }

    /**
     *  Check URL
     *
     *  Checks the URL passed if it is valid or not, in case the browser does
     *  not support HTML5 form fields of type url.
     *
     *  Special shout out to Diago Perini for the wonderful regular expression
     *
     *  @return: boolean
     *
     **/
    function checkURL($url){
        // diago perini valid url REGEX, thank you very much :)
        $diagoperini = '_^(?:(?:https?)://)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS';

        if(preg_match($diagoperini, $url))
            return true;
        else
            return false;
    }

    /**
     *  CreateURL (create key)
     *
     *  Generates an alphanumerical key and returns a #Package object.
     *
     *  @return: Package
     *
     **/
    function createURL($url){
        // create our package
        $pkg = new Package();

        // start the clock for performance
        $start = microtime();
        // make sure key does not already exist, if so, generate a new one
        // until we have a unique key.
        while($this->findKey($key = $this->generateKey()) == true){
            if (self::DEBUG) print_r($key." exists...\n");
        }
        // create the skip list.
        // split the key into the different parts
        $folder = substr($key,0,2);
        $file = substr($key,2,2);
        // the tail of the key is stored together with the url on every line
        $tail = substr($key,4,(SELF::KEYLENGTH-4));
        $path = self::STORAGE.$folder."/".$file;

        // if we don't have a folder for the key storage
        if(!file_exists(self::STORAGE.$folder)){
            mkdir(self::STORAGE.$folder,self::CHMOD, true);
        }

        // check that it is indeed a valid url
        if(!$this->checkURL($url)){
            // if not, let the user know
            $pkg->setMessage("not a valid url");
            $pkg->setStatus(1);
            return $pkg->toJSON();
        }

        // we don't want to store unnecessary "http"
        // if unicode, then it's 4*4*2'116'316'160Bytes or ~ 31.535 GB we'd save
        $url = substr($url, 4);
        $string = "{$tail}\t{$url}\r\n";

        // open file to write mode with pointer at the end of the line to append
        $file_handle = fopen($path, 'a');
        // retry locking file exclusivively for 0.5 seconds
        $retries = 0;
        while(!flock($file_handle, LOCK_EX) && $retries < 10){
            sleep(0.05);
            $retries++;
        }
        // if the lock was achieved
        if($retries < 10){
            // write the line to the end of the file
            fwrite($file_handle, $string) or die('not work');
            // close the file (also releases the lock)
            fclose($file_handle);

            // return the generated key
            $pkg->setMessage("success");
            $pkg->setData($key);
            $pkg->setRuntime(microtime()-$start);
            return $pkg->toJSON();
        }

        // if the retries have gone over and the file was not lockable,
        // generate an error response.
        $pkg->setMessage("could note save entry");
        $pkg->setStatus(2);
        return $pkg->toJSON();

    }


    /**
     *  findKey
     *
     *  User getURL and interpret the responses and return a booelan.
     *
     *  @return: boolean
     *  
     */
    function findKey($key){
        $result = $this->getURL($key);

        // if not locked and not found, it does not exist
        if($result->getMessage() != "locked" && $result->getMessage() != "found")
            return false;
        // if anything else (especially locked) return that they can be found
        else 
            return true;
    }

    /**
     *  getURL
     *
     *  Returns the url from a key.
     *
     *  @return: Package
     */

    function getURL($key){
        // create the communication package
        $pkg = new Package();
        // start the clock for performance
        $start = microtime();
        // generating the params to search through the skip list
        // split the key into the different parts
        $folder = substr($key,0,2);
        $file = substr($key,2,2);
        // the tail of the key is stored together with the url on every line
        $tail = substr($key,4,(SELF::KEYLENGTH-4));
        $path = self::STORAGE.$folder."/".$file;

        // make sure the first, then the second level exists. php searches 
        // recursively through the path, and fails asap.
        if(file_exists($path)){
            $file_handle = fopen($path, 'r');
            //check if the file can be locked for reading
            if(flock($file_handle, LOCK_SH)){
                // php doesn't support any line jumping, so we have to read
                // line by line. in worst case, we have quite a few lines to
                // read from, in case of 6 character key, 36^2 = 1'296 lines
                while ($file_handle && !feof($file_handle)){
                    // get the line
                    $line = fgets($file_handle);
                    // parse the tail of the key
                    $lineTail = substr( $line, 0, SELF::KEYLENGTH-4);
                    // if they match, we have found our key
                    if ($lineTail == $tail){
                        // get the url from the line
                        $lineUrl = substr( $line, SELF::KEYLENGTH-4+1);
                        // let the user know they have a short url and send it
                        $pkg->setMessage("found");
                        $pkg->setRuntime(microtime()-$start);
                        $pkg->setData($lineUrl);
                        return $pkg;
                    }
                }
                // close the file when we are done with it, and release the lock
                fclose($file_handle);
            }
            // if we cannot obtain the lock, let them know
            else{
                $pkg->setMessage("locked");
                $pkg->setData(false);
                return $pkg;
            }
            // if the third level was not found, let them know
            $pkg->setMessage("file {$file} not found");
            $pkg->setData(false);
            return $pkg;
        }
        // if the first or second level of the skip was not found, let them know
        $pkg->setMessage("path {$folder} not found");
        $pkg->setData(false);
        return $pkg;
    }

    /**
     *  generateKey 
     * 
     *  Generates a random alphanumeric key with a specific length.
     *  
     *  @return: String
     *
     **/
    private function generateKey(){
        $key = "";
        for ($i = 0; $i < self::KEYLENGTH; $i++){
            $key .= $this->alpha[rand(0,count($this->alpha)-1)];
        }
        return $key;

    }

}

/**
 *  Creates a unified transport object which stores data and statuses in a
 *  structured approach.
 *
 *  Features
 *   - get and set different data
 *   - can return a json object
 *
 *  @author: Sebastian Boëthius
 *  @email: sebastian@boethius.ch
 *  @date: 2015-05-07
 **/

class Package{
    private $pkg;

    function __construct($data = [], $message = "", $status = 0, $runtime = 0){
        $this->pkg = array(
            // place to store the payload
            "data" => $data,
            // informate string message
            "message" => $message,
            // numerical status, 0 = OK
            "status" => $status,
            // runtime in seconds
            "runtime" => $runtime);
    }

    /**
     *  Getters and Setters for the package object
     **/
    function getData(){
        return $this->pkg["data"];
    }

    function setData($data){
        $this->pkg["data"] = $data;
    }

    function getMessage(){
        return $this->pkg["message"];
    }

    function setMessage($message){
        $this->pkg["message"] = $message;
    }

    function getStatus(){
        return $this->pkg["status"];
    }

    function setStatus($status){
        $this->pkg["status"] = $status;
    }

    function getRuntime(){
        return $this->pkg["runtime"];
    }

    function setRuntime($runtime){
        $this->pkg["runtime"] = $runtime;
    }


    /**
     *  Getters and Setters for the package object
     *
     *  @return: JSON String
     **/
    function toJSON(){
        return json_encode($this->pkg);
    }


}

// instantiate the url shortner.
$url = new URLShortener();


?>