<?php
/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @copyright  Copyright (c) 2009-2010 elements.at New Media Solutions GmbH (http://www.elements.at)
 * @license    http://www.pimcore.org/license     New BSD License
 */

class SearchPhp_AdminController extends Pimcore_Controller_Action_Admin {

    protected $config;

    public function init() {
        parent::init();
        $this->config = SearchPhp_Plugin::getSearchConfigArray();
    }

    public function settingsAction() {

        $language = Zend_Registry::get("Zend_Locale");
        if(is_object($language)){
            $language = $language->__toString();
        }
        $this->view->translate = new Zend_Translate('csv', PIMCORE_PLUGINS_PATH . SearchPhp_Plugin::getTranslationFile($language), $language, array('delimiter' => ','));

        $this->view->config = $this->config;


    }

    public function getFrontendUrlsAction() {

        $urls = explode(",", $this->config['search']['frontend']['urls']);
        $urlArray = array();
        foreach ($urls as $u) {
            if (!empty($u)) {
                $urlArray[] = array("url" => $u);
            }
        }

        $this->_helper->json(array("urls" => $urlArray));
    }

    public function getStateAction(){
        $frontendButtonDisabled = false;
        if(SearchPhp_Plugin::frontendCrawlerRunning() or SearchPhp_Plugin::frontendCrawlerScheduledForStart() or !SearchPhp_Plugin::frontendConfigComplete()){
            $frontendButtonDisabled = true;   
        }


        $message = str_replace("------------------------------------------- ","<br/>",SearchPhp_Plugin::getPluginState());
        
        $frontendStopButtonDIsabled = false;
        if(!SearchPhp_Plugin::frontendConfigComplete() or !SearchPhp_Plugin::frontendCrawlerRunning() or SearchPhp_Plugin::frontendCrawlerStopLocked() ){
            $frontendStopButtonDIsabled=true;
        }

        $this->_helper->json(array("message"=>$message,"frontendButtonDisabled"=>$frontendButtonDisabled,"frontendStopButtonDisabled"=>$frontendStopButtonDIsabled));
    }

    public function stopFrontendCrawlerAction(){
        $playNice=true;
        if($this->_getParam("force")){
            $playNice=false;
        }
        $success = SearchPhp_Plugin::stopFrontendCrawler($playNice,true);
        $this->_helper->json(array("success" => $success));
    }

    public function startFrontendCrawlerAction() {

        SearchPhp_Plugin::forceCrawlerStartOnNextMaintenance("frontend");
        $this->_helper->json(array("success" => true));
    }

    public function getFrontendAllowedAction() {  

        $urls = explode(",", $this->config['search']['frontend']['validLinkRegexes']);
        $urlArray = array();
        foreach ($urls as $u) {
            if (!empty($u)) {
                $urlArray[] = array("regex" => $u);
            }
        }

        $this->_helper->json(array("allowed" => $urlArray));
    }

    public function getFrontendForbiddenAction() {

        $urls = explode(",", $this->config['search']['frontend']['invalidLinkRegexesEditable']);
        $urlArray = array();
        foreach ($urls as $u) {
            if (!empty($u)) {
                $urlArray[] = array("regex" => $u);
            }
        }

        $this->_helper->json(array("forbidden" => $urlArray));
    }


    public function getFrontendCategoriesAction() {

        $urls = explode(",", $this->config['search']['frontend']['categories']);
        $urlArray = array();
        foreach ($urls as $u) {
            if (!empty($u)) {
                $urlArray[] = array("category" => $u);
            }
        }

        $this->_helper->json(array("categories" => $urlArray));
    }

    public function setConfigAction() {
        $values = Zend_Json::decode($this->_getParam("data"));
        
        //general settings

        if(!$this->config["search"]["frontend"]["enabled"] and $values["search.frontend.enabled"]){
            //setting frontend from disabled to enabled
            $this->config["search"]["frontend"]["crawler"]["forceStart"]=1;
        }

        $this->config["search"]["frontend"]["enabled"] = 0;
        if ($values["search.frontend.enabled"]) {
            $this->config["search"]["frontend"]["enabled"] = 1;
        }

        //frontend settings
        $this->config["search"]["frontend"]["ignoreLanguage"] = 0;
        if ($values["search.frontend.ignoreLanguage"]) {
            $this->config["search"]["frontend"]["ignoreLanguage"] = 1;
        }

        $this->config["search"]["frontend"]["fuzzySearch"] = 0;
        if ($values["search.frontend.fuzzySearch"]) {
            $this->config["search"]["frontend"]["fuzzySearch"] = 1;
        }

        $this->config["search"]["frontend"]["ownHostOnly"] = 0;
        if ($values["search.frontend.ownHostOnly"]) {
            $this->config["search"]["frontend"]["ownHostOnly"] = 1;
        }

        if (is_numeric($values["search.frontend.crawler.maxThreads"])) {
            $this->config["search"]["frontend"]["crawler"]["maxThreads"]=$values["search.frontend.crawler.maxThreads"];            
        }

        if (is_numeric($values["search.frontend.crawler.maxLinkDepth"])) {
            $this->config["search"]["frontend"]["crawler"]["maxLinkDepth"]=$values["search.frontend.crawler.maxLinkDepth"];
        } else {
             $this->config["search"]["frontend"]["crawler"]["maxLinkDepth"] = 15;           
        }

        $this->config["search"]["frontend"]["categories"] = base64_encode($values["search.frontend.categories"]);
        $this->config["search"]["frontend"]["urls"] = base64_encode($values["search.frontend.urls"]);
        $this->config["search"]["frontend"]["validLinkRegexes"] = base64_encode($values["search.frontend.validLinkRegexes"]);
        $this->config["search"]["frontend"]["invalidLinkRegexesEditable"] = base64_encode($values["search.frontend.invalidLinkRegexesEditable"]);
        $this->config["search"]["frontend"]["crawler"]["contentStartIndicator"] = base64_encode($values["search.frontend.crawler.contentStartIndicator"]);
        $this->config["search"]["frontend"]["crawler"]["contentEndIndicator"] = base64_encode($values["search.frontend.crawler.contentEndIndicator"]);
        try {
            $config = new Zend_Config($this->config, true);
            $writer = new Zend_Config_Writer_Xml(array(
                "config" => $config,
                "filename" => PIMCORE_WEBSITE_PATH."/var/search/search.xml"
            ));
            $writer->write();
            $this->_helper->json(array("success" => true));
        } catch (Exception $e) {
            $this->_helper->json(array("success" => false, "message" => $e->getMessage()));

        }

        

    }

  

}