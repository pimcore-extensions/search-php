<?php
        include_once("../../../pimcore/config/startup.php");
Pimcore::initAutoloader();
Pimcore::initConfiguration();
Pimcore::initLogger();
Pimcore::initPlugins();

ini_set('memory_limit', '2048M');
ini_set("max_execution_time", "-1");


logger::log("SearchPhp_Plugin: Starting crawl", Zend_Log::DEBUG);

//TODO nix specific
exec("rm -Rf ".str_replace("/index","/tmpindex",$indexDir)." ".$indexDir);


$confArray = SearchPhp_Plugin::getSearchConfigArray();

$urls = explode(",", $confArray['search']['frontend']['urls']);
$validLinkRegexes = explode(",", $confArray['search']['frontend']['validLinkRegexes']);
$invalidLinkRegexes = explode(",", $confArray['search']['frontend']['invalidLinkRegexes']);

$rawConfig = new Zend_Config_Xml(PIMCORE_PLUGINS_PATH . SearchPhp_Plugin::$configFile);
$rawConfigArray = $rawConfig->toArray();

$rawConfigArray['search']['frontend']['crawler']['running'] = 1;
$rawConfigArray['search']['frontend']['crawler']['started'] = time();

$config = new Zend_Config($rawConfigArray, true);
$writer = new Zend_Config_Writer_Xml(array(
    "config" => $config,
    "filename" => PIMCORE_PLUGINS_PATH . SearchPhp_Plugin::$configFile
));
$writer->write();

$crawler = new SearchPhp_Frontend_Crawler($validLinkRegexes, $invalidLinkRegexes,10, 30, $confArray['search']['frontend']['crawler']['contentStartIndicator'],$confArray['search']['frontend']['crawler']['contentEndIndicator']);
$crawler->findLinks($urls);


$rawConfig = new Zend_Config_Xml(PIMCORE_PLUGINS_PATH . SearchPhp_Plugin::$configFile);
$rawConfigArray = $rawConfig->toArray();

$rawConfigArray['search']['frontend']['crawler']['running'] = 0;
$rawConfigArray['search']['frontend']['crawler']['finished'] = time();
$config = new Zend_Config($rawConfigArray, true);
$writer = new Zend_Config_Writer_Xml(array(
    "config" => $config,
    "filename" => PIMCORE_PLUGINS_PATH . SearchPhp_Plugin::$configFile
));
$writer->write();



logger::log("SearchPhp_Plugin: replacing old index ...", Zend_Log::DEBUG);
$indexDir = SearchPhp_Plugin::getFrontendSearchIndex();

//TODO nix specific
exec("rm -Rf ".$indexDir);
exec("mv ".str_replace("/index","/tmpindex",$indexDir)." ".$indexDir);

logger::log("Search_PluginPhp: replaced old index", Zend_Log::DEBUG);

logger::log("Search_PluginPhp: Finished crawl", Zend_Log::DEBUG);

       
