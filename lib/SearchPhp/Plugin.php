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

class SearchPhp_Plugin extends Pimcore_API_Plugin_Abstract implements Pimcore_API_Plugin_Interface
{


    public function __construct($jsPaths = null, $cssPaths = null, $alternateIndexDir = null)
    {

        parent::__construct($jsPaths, $cssPaths);

        if (!$this->isInstalled()) {
            logger::err(get_class($this) . ": SearchPhp plugin not installed ");
        }
    }

    /**
     * @static
     * @return array
     */
    public static function getSearchConfigArray()
    {

        $config = new Zend_Config_Xml(PIMCORE_WEBSITE_PATH . "/var/search/search.xml");
        $config = $config->toArray();
        $config["search"]["frontend"]["categories"] = base64_decode($config["search"]["frontend"]["categories"]);
        $config["search"]["frontend"]["urls"] = base64_decode($config["search"]["frontend"]["urls"]);
        $config["search"]["frontend"]["validLinkRegexes"] = base64_decode($config["search"]["frontend"]["validLinkRegexes"]);
        $config["search"]["frontend"]["invalidLinkRegexesEditable"] = base64_decode($config["search"]["frontend"]["invalidLinkRegexesEditable"]);
        $config["search"]["frontend"]["crawler"]["contentStartIndicator"] = base64_decode($config["search"]["frontend"]["crawler"]["contentStartIndicator"]);
        $config["search"]["frontend"]["crawler"]["contentEndIndicator"] = base64_decode($config["search"]["frontend"]["crawler"]["contentEndIndicator"]);

        return $config;
    }

    /**
     * @static
     * @return string
     */
    public static function getPluginState()
    {
        $language = "en";
        try {
            $locale = Zend_Registry::get("Zend_Locale");
            if ($locale instanceof Zend_Locale) {
                $language = $locale->getLanguage();
            }

        } catch (Exception $e) {
        }


        $translate = new Zend_Translate('csv', PIMCORE_PLUGINS_PATH . self::getTranslationFile($language), $language, array('delimiter' => ','));


        if (self::isInstalled()) {

            $confArray = self::getSearchConfigArray();
            $message = "";
            if ($confArray['search']['frontend']['enabled']) {
                if ($confArray['search']['frontend']['crawler']['running']) {
                    $message .= $translate->_("searchphp_frontend_crawler_running") . " ";
                } else {
                    $message .= $translate->_("searchphp_frontend_crawler_not_running") . " ";
                }

                $message .= $translate->_("SearchPhp_Frontend_Crawler_last_started") . " " . date('d.m.Y H:i', (double)$confArray['search']['frontend']['crawler']['started']) . " ";
                $message .= $translate->_("SearchPhp_Frontend_Crawler_last_finished") . " " . date('d.m.Y H:i', (double)$confArray['search']['frontend']['crawler']['finished']) . " ";
                if (!self::frontendConfigComplete()) {
                    $message .= " -------------------------------------------- ";
                    $message .= 'ERROR:' . $translate->_('searchphp_frontend_config_incomplete');
                } else {
                    if ($confArray['search']['frontend']['crawler']['forceStart']) {
                        $message .= "------------------------------------------- ";
                        $message .= $translate->_("searchphp_frontend_crawler") . ": ";
                        $message .= $translate->_("SearchPhp_Frontend_Crawler_start_on_next_maintenance");
                    }
                }
                $message .= " -------------------------------------------- ";
            }
            return $message;
        } else {
            if (Pimcore_Version::$revision < 930) {
                return $translate->_("searchphp_pimcore_version_too_low");
            }
            return "";
        }
    }


    /**
     *  indicates whether this plugins is currently installed
     * @return boolean $isInstalled
     */
    public static function isInstalled()
    {
        $indexDir = self::getFrontendSearchIndex();
        return ($indexDir != null and is_dir($indexDir));
    }

    /**
     * @return boolean $readyForInstall
     */
    public static function readyForInstall()
    {

        $readyForInstall = true;


        if (!is_dir(PIMCORE_WEBSITE_PATH . "/var/tmp") or !is_writable(PIMCORE_WEBSITE_PATH . "/var/tmp")) {
            $readyForInstall = false;
        }
        if (!is_dir(PIMCORE_WEBSITE_PATH . "/var") or !is_writable(PIMCORE_WEBSITE_PATH . "/var")) {
            $readyForInstall = false;
        }
        if (!is_writable(PIMCORE_PLUGINS_PATH)) {
            $readyForInstall = false;
        }
        if (Pimcore_Version::$revision < 930) {
            $readyForInstall = false;
        }


        return $readyForInstall;
    }

    /**
     * @static
     * @return string
     */
    public static function getTranslationFileDirectory()
    {
        return PIMCORE_PLUGINS_PATH . "/SearchPhp/texts";
    }

    /**
     *
     * @param string $language
     * @return string path to the translation file relative to plugin direcory
     */
    public static function getTranslationFile($language)
    {

        if (is_file(PIMCORE_PLUGINS_PATH . "/SearchPhp/texts/" . $language . ".csv")) {
            return "/SearchPhp/texts/" . $language . ".csv";
        } else {
            return "/SearchPhp/texts/en.csv";
        }
    }


    /**
     * Reads the location for the frontend search index from search config file and returns path if exists
     *
     * @return string $path
     */
    public static function getFrontendSearchIndex()
    {


        if (file_exists(PIMCORE_WEBSITE_PATH . "/var/search/search.xml")) {

            $searchConf = new Zend_Config_Xml(PIMCORE_WEBSITE_PATH . "/var/search/search.xml");
            if ($searchConf != null and !empty($searchConf->search->frontend->index)) {
                if (is_dir($searchConf->search->frontend->index)) {
                    return $searchConf->search->frontend->index;
                } else if (is_dir(PIMCORE_DOCUMENT_ROOT . "/" . $searchConf->search->frontend->index)) {
                    return PIMCORE_DOCUMENT_ROOT . "/" . $searchConf->search->frontend->index;
                } else return null;
            }

        } else {
            logger::err("Search_Plugin: Could not read search config.");
        }

    }

    /**
     *  install function
     * @return string $message statusmessage to display in frontend
     */
    public static function install()
    {

        $translate = new Zend_Translate('csv', PIMCORE_PLUGINS_PATH . self::getTranslationFile('en'), 'en', array('delimiter' => ','));
        $message = "";

        //create folder for search in website
        mkdir(PIMCORE_WEBSITE_PATH . "/var/search", 0755, true);

        //set up search config
        $searchConf = '<?xml version="1.0"?>
            <zend-config xmlns:zf="http://framework.zend.com/xml/zend-config-xml/1.0/">
              <search>
                <frontend>
                  <index>website/var/search/frontend/index/</index>
                  <ignoreLanguage>1</ignoreLanguage>
                  <fuzzySearch>1</fuzzySearch>
                  <enabled>0</enabled>
                  <urls></urls>
                  <validLinkRegexes></validLinkRegexes>
                  <invalidLinkRegexesEditable></invalidLinkRegexesEditable>
                  <invalidLinkRegexes>@.*\.(js|JS|gif|GIF|jpg|JPG|png|PNG|ico|ICO|eps|jpeg|JPEG|bmp|BMP|css|CSS|sit|wmf|zip|ppt|mpg|xls|gz|rpm|tgz|mov|MOV|exe|mp3|MP3|kmz|gpx|kml|swf|SWF)$@</invalidLinkRegexes>
                  <categories></categories>
                  <crawler>
                    <maxThreads>20</maxThreads>
                    <maxLinkDepth>15</maxLinkDepth>
                    <contentStartIndicator></contentStartIndicator>
                    <contentEndIndicator></contentEndIndicator>
                    <forceStart>0</forceStart>
                    <running>0</running>
                    <started></started>
                    <finished></finished>
                    <forceStop>0</forceStop>
                    <forceStopInitiated></forceStopInitiated>
                  </crawler>
                  <ownHostOnly>0</ownHostOnly>
                </frontend>
              </search>
            </zend-config>';
        file_put_contents(PIMCORE_WEBSITE_PATH . "/var/search/search.xml", $searchConf);

        if (file_exists(PIMCORE_WEBSITE_PATH . "/var/search/search.xml")) {
            $searchConf = new Zend_Config_Xml(PIMCORE_WEBSITE_PATH . "/var/search/search.xml");
            if ($searchConf->search->frontend->enabled) {
                self::forceCrawlerStartOnNextMaintenance("frontend");
            }

            $index = PIMCORE_DOCUMENT_ROOT . "/" . $searchConf->search->frontend->index;
            //create frontend search index dir
            if (!empty($index) and !is_dir($index)) {

                $success = mkdir($index, 0755, true);
                chmod($index, 0755);
                if ($success) {
                    $message .= $translate->_("created_frontend_index_dir");
                } else {
                    $message .= $translate->_("could_not_create_frontend_index_dir");
                }

            } else {
                $message .= $translate->_("frontend_index_dir_not_configured");
            }

        } else {
            $message .= $translate->_("failed_to_setup_search_config");
        }

        //add redirect for sitemap.xml
        $redirect = new Redirect();
        $redirect->setValues(array("source" => "/\/sitemap.xml/", "target" => "/plugin/SearchPhp/frontend/sitemap", "statusCode" => 301, "priority" => 10));
        $redirect->save();

        return $message;

    }

    /**
     * uninstall function
     * @return string $message status message to display in frontend
     */
    public static function uninstall()
    {

        $language = "en";
        try {
            $locale = Zend_Registry::get("Zend_Locale");
            if ($locale instanceof Zend_Locale) {
                $language = $locale->getLanguage();
            }

        } catch (Exception $e) {
        }


        $translate = new Zend_Translate('csv', PIMCORE_PLUGINS_PATH . self::getTranslationFile($language), $language, array('delimiter' => ','));


        $index = self::getFrontendSearchIndex();
        $success = false;

        if (!empty($index)) {
            $success = self::deleteDirectory($index);
        }
        if ($success) {
            return $translate->_("uninstalled_successfully");
        } else {
            return $translate->_("uninstall_failed");
        }

    }

    /**
     *
     * @param string $dir
     * @return boolean $success
     */
    private static function deleteDirectory($dir)
    {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        logger::info("removing " . $dir);
        return rmdir($dir);
    }


    /**
     * @return bool
     */
    public static function frontendCrawlerRunning()
    {
        $configArray = self::getSearchConfigArray();
        if ($configArray['search']['frontend']['crawler']['running']) return true;
        else return false;
    }

    /**
     * @static
     * @return bool
     */
    public static function frontendCrawlerStopLocked()
    {
        $configArray = self::getSearchConfigArray();
        if ($configArray['search']['frontend']['crawler']['forceStop']) return true;
        else return false;
    }

    /**
     * @return bool
     */
    public static function frontendCrawlerScheduledForStart()
    {
        $configArray = self::getSearchConfigArray();
        if ($configArray['search']['frontend']['crawler']['forceStart']) return true;
        else return false;
    }

    /**
     * @static
     * @return boolean
     */
    public static function frontendConfigComplete()
    {
        $configArray = self::getSearchConfigArray();
        if (is_array($configArray) and !empty($configArray['search']['frontend']['urls']) and !empty($configArray['search']['frontend']['validLinkRegexes'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @static
     * @param bool $playNice
     * @return bool
     */
    public static function stopFrontendCrawler($playNice = true, $isFrontendCall = false)
    {

        logger::debug("SearchPhp_Plugin: forcing frontend crawler stop, play nice: [ $playNice ]");
        self::setStopLock("frontend", true);

        //just to make sure nothing else starts the crawler right now
        self::setCrawlerState("frontend", "started", false);

        $configArray = self::getSearchConfigArray();
        $maxThreads = $configArray['search']['frontend']['crawler']['maxThreads'];

        $db = Pimcore_Resource_Mysql::get();
        $db->query("DROP TABLE IF EXISTS `plugin_searchphp_frontend_crawler_todo`;");
        $db->query("DROP TABLE IF EXISTS `plugin_searchphp_indexer_todo`;");

        logger::debug("SearchPhp_Plugin: forcing frontend crawler stop - dropped tables");

        sleep(1);

        $pidFiles = array("maintainance_crawler-indexer");
        for ($i = 1; $i <= $maxThreads; $i++) {
            $pidFiles[] = "maintainance_crawler-" . $i;
        }

        $counter = 1;
        while ($pidFiles and count($pidFiles) > 0 and $counter < 10) {
            sort($pidFiles);
            for ($i = 0; $i < count($pidFiles); $i++) {
                $file = PIMCORE_SYSTEM_TEMP_DIRECTORY . "/" . $pidFiles[$i];
                if (!is_file($file)) {
                    unset($pidFiles[$i]);
                }
            }
            sleep(1);
            $counter++;
        }

        if (!$playNice) {


            if (is_file(PIMCORE_SYSTEM_TEMP_DIRECTORY . "/maintainance_SearchPhp_Plugin.pid" and $isFrontendCall)) {
                $pidFiles[] = "maintainance_SearchPhp_Plugin.pid";
            }

            //delete pid files of all  processes
            for ($i = 0; $i < count($pidFiles); $i++) {
                $file = PIMCORE_SYSTEM_TEMP_DIRECTORY . "/" . $pidFiles[$i];
                if (is_file($file) and !unlink($file)) {
                    logger::emerg("SearchPhp_Plugin: : Trying to force stop crawler, but cannot delete [ $file ]");
                }

                if (!is_file($file)) {
                    unset($pidFiles[$i]);
                }
            }
        }
        self::setStopLock("frontend", false);
        if (!$pidFiles or count($pidFiles) == 0) {
            self::setCrawlerState("frontend", "finished", false);
            return true;
        }
        return false;

    }

    /**
     * @param  $configArray
     * @return void
     */
    public function frontendCrawl($configArray = null)
    {

        if (!is_array($configArray)) {
            $configArray = self::getSearchConfigArray();
        }

        if (self::frontendConfigComplete()) {
            ini_set('memory_limit', '2048M');
            ini_set("max_execution_time", "-1");

            $indexDir = self::getFrontendSearchIndex();
            if ($indexDir) {

                //TODO nix specific
                exec("rm -Rf " . str_replace("/index/", "/tmpindex", $indexDir));
                logger::debug("rm -Rf " . str_replace("/index/", "/tmpindex", $indexDir));
                try {
                    $urls = explode(",", $configArray['search']['frontend']['urls']);
                    $validLinkRegexes = explode(",", $configArray['search']['frontend']['validLinkRegexes']);

                    $invalidLinkRegexesSystem = $configArray['search']['frontend']['invalidLinkRegexes'];
                    $invalidLinkRegexesEditable = $configArray['search']['frontend']['invalidLinkRegexesEditable'];
                    if (!empty($invalidLinkRegexesEditable) and !empty($invalidLinkRegexesSystem)) {
                        $invalidLinkRegexes = explode(",", $invalidLinkRegexesEditable . "," . $invalidLinkRegexesSystem);
                    } else if (!empty($invalidLinkRegexesEditable)) {
                        $invalidLinkRegexes = explode(",", $invalidLinkRegexesEditable);
                    } else if (!empty($invalidLinkRegexesSystem)) {
                        $invalidLinkRegexes = explode(",", $invalidLinkRegexesSystem);
                    } else {
                        $invalidLinkRegexes = array();
                    }

                    self::setCrawlerState("frontend", "started", true);
                    $maxLinkDepth = $configArray['search']['frontend']['crawler']['maxLinkDepth'];
                    if (is_numeric($maxLinkDepth) and $maxLinkDepth > 0) {
                        $crawler = new SearchPhp_Frontend_Crawler($validLinkRegexes, $invalidLinkRegexes, 10, 30, $configArray['search']['frontend']['crawler']['contentStartIndicator'], $configArray['search']['frontend']['crawler']['contentEndIndicator'], $configArray['search']['frontend']['crawler']['maxThreads'], $maxLinkDepth);
                    } else {
                        $crawler = new SearchPhp_Frontend_Crawler($validLinkRegexes, $invalidLinkRegexes, 10, 30, $configArray['search']['frontend']['crawler']['contentStartIndicator'], $configArray['search']['frontend']['crawler']['contentEndIndicator'], $configArray['search']['frontend']['crawler']['maxThreads']);
                    }
                    $crawler->findLinks($urls);

                    self::setCrawlerState("frontend", "finished", false);

                    logger::debug("SearchPhp_Plugin: replacing old index ...");

                    $db = Pimcore_Resource_Mysql::get();
                    $db->query("DROP TABLE IF EXISTS `plugin_searchphp_contents`;");
                    $db->query("RENAME TABLE `plugin_searchphp_contents_temp` TO `plugin_searchphp_contents`;");

                    //TODO nix specific
                    exec("rm -Rf " . $indexDir);
                    logger::debug("rm -Rf " . $indexDir);
                    $tmpIndex = str_replace("/index", "/tmpindex", $indexDir);
                    exec("cp -R " . substr($tmpIndex, 0, -1) . " " . substr($indexDir, 0, -1));
                    logger::debug("cp -R " . substr($tmpIndex, 0, -1) . " " . substr($indexDir, 0, -1));
                    logger::debug("SearchPhp_Plugin: replaced old index");
                    logger::info("SearchPhp_Plugin: Finished crawl");
                } catch (Exception $e) {
                    logger::err($e);
                    throw $e;
                }
            }
        } else {
            logger::info("SearchPhp_Plugin: Did not start frontend crawler, because config incomplete");
        }
    }

    /**
     * @param  $crawler frontend | backend
     * @return void
     */
    public static function forceCrawlerStartOnNextMaintenance($crawler)
    {
        $config = new Zend_Config_Xml(PIMCORE_WEBSITE_PATH . "/var/search/search.xml");
        $configArray = $config->toArray();
        $configArray['search'][$crawler]['crawler']['forceStart'] = 1;
        $config = new Zend_Config($configArray, true);
        $writer = new Zend_Config_Writer_Xml(array(
                                                  "config" => $config,
                                                  "filename" => PIMCORE_WEBSITE_PATH . "/var/search/search.xml"
                                             ));
        $writer->write();
    }


    /**
     * @param  string $crawler frontend | backend
     * @param string $action started | finished
     * @param bool $running
     * @return void
     */
    protected static function setCrawlerState($crawler, $action, $running, $setTime = true)
    {

        $config = new Zend_Config_Xml(PIMCORE_WEBSITE_PATH . "/var/search/search.xml");
        $configArray = $config->toArray();
        $run = 0;
        if ($running) $run = 1;
        $configArray['search'][$crawler]['crawler']['forceStart'] = 0;
        $configArray['search'][$crawler]['crawler']['running'] = $run;
        if ($setTime) {
            $configArray['search'][$crawler]['crawler'][$action] = time();
        }
        $config = new Zend_Config($configArray, true);
        $writer = new Zend_Config_Writer_Xml(array(
                                                  "config" => $config,
                                                  "filename" => PIMCORE_WEBSITE_PATH . "/var/search/search.xml"
                                             ));
        $writer->write();
    }


    protected static function setStopLock($crawler, $flag = true)
    {
        $stop = 1;
        if (!$flag) {
            $stop = 0;
        }

        $config = new Zend_Config_Xml(PIMCORE_WEBSITE_PATH . "/var/search/search.xml");
        $configArray = $config->toArray();
        $configArray['search'][$crawler]['crawler']['forceStop'] = $stop;
        if ($stop) {
            $configArray['search'][$crawler]['crawler']['forceStopInitiated'] = time();
        }
        $config = new Zend_Config($configArray, true);
        $writer = new Zend_Config_Writer_Xml(array(
                                                  "config" => $config,
                                                  "filename" => PIMCORE_WEBSITE_PATH . "/var/search/search.xml"
                                             ));
        $writer->write();
    }

    /**
     * Hook called when maintenance script is called
     */
    public function maintenance()
    {

        if (self::isInstalled()) {
            $currentHour = date("H", time());
            $configArray = self::getSearchConfigArray();

            //Frontend recrawl
            $lastStarted = $configArray['search']['frontend']['crawler']['started'];
            $lastFinished = $configArray['search']['frontend']['crawler']['finished'];
            $running = $configArray['search']['frontend']['crawler']['running'];
            $aDayAgo = time() - (24 * 60 * 60);
            $forceStart = $configArray['search']['frontend']['crawler']['forceStart'];

            if ($configArray['search']['frontend']['enabled'] and ((!$running and $lastStarted <= $aDayAgo and $currentHour > 1 and $currentHour < 3) or $forceStart)) {
                logger::debug("starting frontend recrawl...");
                $this->frontendCrawl($configArray);
                SearchPhp_Tool::generateSitemap();
            } else if ($running and ($lastFinished <= ($aDayAgo))) {
                //there seems to be a problem
                if ($lastFinished <= ($aDayAgo)) {
                    logger::err("Search_PluginPhp: There seems to be a problem with the search crawler! Trying to stop it.");
                }
                $this->stopFrontendCrawler(false, false);
            }
        } else {
            logger::debug("SearchPhp Plugin is not installed - no maintaince to do for this plugin.");
        }
    }


    /**
     *
     * @param string $queryStr
     * @param Zend_Search_Lucene_Interface $index
     * @return Array $hits
     */
    public static function wildcardFindTerms($queryStr, $index)
    {

        if ($index != null) {

            $pattern = new Zend_Search_Lucene_Index_Term($queryStr . '*');
            $userQuery = new Zend_Search_Lucene_Search_Query_Wildcard($pattern);
            Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength(2);
            $index->find($userQuery);
            $terms = $userQuery->getQueryTerms();

            return $terms;
        }
    }


    /**
     *  finds similar terms
     * @param string $queryStr
     * @param Zend_Search_Lucene_Interface $index
     * @param integer $prefixLength optionally specify prefix lengh, default 0
     * @param float $similarity optionally specify similarity, default 0.5
     * @return string[] $similarSearchTerms
     */
    public static function fuzzyFindTerms($queryStr, $index, $prefixLengh = 0, $similarity = 0.5)
    {

        if ($index != null) {

            Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength($prefixLengh);
            $term = new Zend_Search_Lucene_Index_Term($queryStr);
            $fuzzyQuery = new Zend_Search_Lucene_Search_Query_Fuzzy($term, $similarity);


            $hits = $index->find($fuzzyQuery);
            $terms = $fuzzyQuery->getQueryTerms();

            return $terms;
        }
    }

}

