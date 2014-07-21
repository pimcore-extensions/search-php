<?php

class SearchPhp_FrontendController extends Website_Controller_Action
{

    protected $frontendIndex;
    protected $searchLanguage;
    protected $ownHostOnly = false;
    protected $categories = array();

    public function init()
    {

        parent::init();

        if (file_exists(PIMCORE_WEBSITE_PATH . "/var/search/search.xml")) {
            $searchConf = new Zend_Config_Xml(PIMCORE_WEBSITE_PATH . "/var/search/search.xml");

            if ($searchConf->search->frontend->enabled) {
                try {
                    Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
                    $this->frontendIndex = Zend_Search_Lucene::open(SearchPhp_Plugin::getFrontendSearchIndex());
                    $this->categories = explode(",", $searchConf->search->frontend->categories);

                    if ($searchConf->search->frontend->ignoreLanguage != '1') {
                        $this->searchLanguage = $this->_getParam("language");
                        if (empty($this->searchLanguage)) {
                            try {
                                $this->searchLanguage = Zend_Registry::get("Zend_Locale");
                            } catch (Exception $e) {
                                $this->searchLanguage = "en";
                            }

                        }
                    } else  $this->searchLanguage = null;

                    $this->fuzzySearch = false;
                    if ($searchConf->search->frontend->fuzzySearch == '1') {
                        $this->fuzzySearch = true;
                    }

                    if ($searchConf->search->frontend->ownHostOnly) {
                        $this->ownHostOnly = true;
                    }
                } catch (Exception $e) {
                    //p_r($e);
                    throw new Exception("could not open index");
                }
            } else {
                throw new Exception("search frontend is not installed and enabled.");
            }
        }
    }

    public function sitemapAction()
    {

        $this->removeViewRenderer();

        $sitemapFile = $this->_getParam("sitemap");
        if (strpos($sitemapFile, '/') !== FALSE) {
            // / not allowed since site map file name is generated from domain name
            throw new Exception(get_class($this) . ": Attempted access to invalid sitemap [ $sitemapFile ]");
        }
        header("Content-type: application/xml");
        $requestedSitemap = PIMCORE_WEBSITE_PATH . "/var/search/sitemap/" . $sitemapFile;
        $indexSitemap = PIMCORE_WEBSITE_PATH . "/var/search/sitemap/sitemap.xml";

        if ($this->_getParam("sitemap") and is_file($requestedSitemap)) {
            $content = file_get_contents($requestedSitemap);
            //TODO: strlen($content) takes a few seconds!
            //header("Content-Length: ".strlen($content));
            echo $content;
            exit;
        } else if (is_file($indexSitemap)) {
            $content = file_get_contents($indexSitemap);
            //TODO: strlen($content) takes a few seconds!
            //header("Content-Length: ".strlen($content));           
            echo $content;
            exit;
        } else {
            logger::debug(get_class($this) . ": sitemap request - but no sitemap available to deliver");
            exit;
        }


    }

    public function autocompleteAction()
    {

        $queryFromRequest = $this->cleanRequestString($this->_getParam("q"));
        $categoryFromRequest = $this->cleanRequestString($this->_getParam("cat"));

        $terms = SearchPhp_Plugin::wildcardFindTerms(strtolower($queryFromRequest), $this->frontendIndex);

        if (empty($terms)) {
            $terms = SearchPhp_Plugin::fuzzyFindTerms(strtolower($queryFromRequest), $this->frontendIndex);
        }

        $data = array();
        $suggestions = array();
        $counter = 1;


        if ($this->searchLanguage != null) {
            if (is_object($this->searchLanguage)) {
                $language = $this->searchLanguage->toString();
            } else {
                $language = $this->searchLanguage;
            }
            $language = str_replace(array("_", "-"), "", $language);

        }

        foreach ($terms as $term) {
            $t = $term->text;

            //check if term can be found for current language
            $hits = null;
            if (!empty($language) or !empty($categoryFromRequest)) {
                $query = new Zend_Search_Lucene_Search_Query_Boolean();

                if ($language != null) {
                    $languageTerm = new Zend_Search_Lucene_Index_Term($language, 'lang');
                    $languageQuery = new Zend_Search_Lucene_Search_Query_Term($languageTerm);
                    $query->addSubquery($languageQuery, true);
                }

                if (!empty($categoryFromRequest)) {
                    $categoryTerm = new Zend_Search_Lucene_Index_Term($categoryFromRequest, 'cat');
                    $categoryQuery = new Zend_Search_Lucene_Search_Query_Term($categoryTerm);
                    $query->addSubquery($categoryQuery, true);
                }

                $userQuery = Zend_Search_Lucene_Search_QueryParser::parse($t, 'utf-8');
                $query->addSubquery($userQuery, true);

                $hits = $this->frontendIndex->find($query);
                $validHits = array();
                if ($this->ownHostOnly and $hits != null) {
                    //get rid of hits from other hosts
                    $currenthost = $_SERVER['HTTP_HOST'];
                    for ($i = 0; $i < (count($hits)); $i++) {
                        $url = $hits[$i]->getDocument()->getField("url");
                        if (strpos($url->value, "http://" . $currenthost) !== FALSE) {
                            $validHits[] = $hits[$i];
                        }
                    }
                } else {
                    $validHits = $hits;
                }
            } else {
                $validHits[] = $t;
            }
            if (count($validHits) > 0 and !in_array($t, $suggestions)) {

                $suggestions[] = $t;
                if ($counter >= 10) break;
                $counter++;
            }
        }


        $this->removeViewRenderer();
        foreach ($suggestions as $suggestion) {
            echo $suggestion . "\r\n";
        }

    }

    public function findAction()
    {


        $queryFromRequest = $this->cleanRequestString($_REQUEST["query"]);
        $categoryFromRequest = $this->cleanRequestString($_REQUEST["cat"]);

        $searcher = new SearchPhp_Frontend_Searcher();

        $this->view->groupByCategory = $this->_getParam("groupByCategory");
        $this->view->omitSearchForm = $this->_getParam("omitSearchForm");
        $this->view->categoryOrder = $this->_getParam("categoryOrder");
        $this->view->omitJsIncludes = $this->_getParam("omitJsIncludes");

        $perPage = $this->_getParam("perPage");
        if (empty($perPage)) {
            $perPage = 10;
        }

        $page = $this->_getParam("page");
        if (empty($page)) {
            $page = 1;
        }

        $queryStr = strtolower($queryFromRequest);
        $this->view->category = $categoryFromRequest;
        if (!empty($this->view->category)) {
            $category = $this->view->category;
        } else $category = null;
        $pluginConf = SearchPhp_Plugin::getSearchConfigArray();
        if (!empty($pluginConf["search"]["frontend"]["categories"])) {
            $this->view->availableCategories = explode(",", $pluginConf["search"]["frontend"]["categories"]);

        }

        $doFuzzy = $this->_getParam("fuzzy");
        try {
            $query = new Zend_Search_Lucene_Search_Query_Boolean();

            $field = $this->_getParam("field");
            if (!empty($field)) {
                Zend_Search_Lucene::setDefaultSearchField($field);
            }

            $searchResults = array();
            if (!empty($queryStr)) {

                if ($doFuzzy) {
                    $queryStr = str_replace(" ", "~ ", $queryStr);
                    $queryStr .= "~";
                    Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength(3);
                }

                $userQuery = Zend_Search_Lucene_Search_QueryParser::parse($queryStr, 'utf-8');
                $query->addSubquery($userQuery, true);


                if (!empty($this->searchLanguage)) {
                    if (is_object($this->searchLanguage)) {
                        $lang = $this->searchLanguage->toString();
                    } else {
                        $lang = $this->searchLanguage;
                    }
                    $lang = str_replace(array("_", "-"), "", $lang);
                    $languageTerm = new Zend_Search_Lucene_Index_Term($lang, 'lang');
                    $languageQuery = new Zend_Search_Lucene_Search_Query_Term($languageTerm);
                    $query->addSubquery($languageQuery, true);
                }

                if (!empty($category)) {
                    $categoryTerm = new Zend_Search_Lucene_Index_Term($category, 'cat');
                    $categoryQuery = new Zend_Search_Lucene_Search_Query_Term($categoryTerm);
                    $query->addSubquery($categoryQuery, true);
                }

                $hits = $this->frontendIndex->find($query);

                $validHits = array();
                if ($this->ownHostOnly and $hits != null) {
                    //get rid of hits from other hosts
                    $currenthost = $_SERVER['HTTP_HOST'];
                    if (count($hits) == 1) {
                        $url = $hits[0]->getDocument()->getField("url");
                        if (strpos($url->value, "http://" . $currenthost) !== FALSE || strpos($url->value, "https://" . $currenthost) !== FALSE) {
                            $validHits[] = $hits[0];
                        }
                    }

                    for ($i = 0; $i < (count($hits)); $i++) {
                        $url = $hits[$i]->getDocument()->getField("url");
                        if (strpos($url->value, "http://" . $currenthost) !== FALSE || strpos($url->value, "https://" . $currenthost) !== FALSE) {
                            $validHits[] = $hits[$i];
                        }
                    }
                } else {
                    $validHits = $hits;
                }


                $start = $perPage * ($page - 1);
                $end = $start + ($perPage - 1);
                if ($end > count($validHits) - 1) {
                    $end = count($validHits) - 1;
                }

                for ($i = $start; $i <= $end; $i++) {
                    $hit = $validHits[$i];

                    $url = $hit->getDocument()->getField("url");
                    $title = $hit->getDocument()->getField("title");


                    $searchResult['boost'] = $hit->getDocument()->boost;

                    $searchResult['title'] = $title->value;
                    $searchResult['url'] = $url->value;
                    $searchResult['sumary'] = $searcher->getSumaryForUrl($url->value, $queryStr);

                    try {
                        if ($hit->getDocument()->getField("h1")) {
                            $searchResult['h1'] = $hit->getDocument()->getField("h1")->value;
                        }
                    } catch (Zend_Search_Lucene_Exception $e) {
                    }


                    foreach ($this->categories as $category) {
                        try {
                            $searchResult['categories'][] = $hit->getDocument()->getField("cat")->value;
                        } catch (Zend_Search_Lucene_Exception $e) {
                        }
                    }

                    $searchResults[] = $searchResult;
                    unset($searchResult);

                }


            }


            if (count($validHits) < 1) {
                $this->view->pages = 0;
            } else {
                $this->view->pages = ceil(count($validHits) / $perPage);
            }
            $this->view->perPage = $perPage;
            $this->view->page = $page;
            $this->view->total = count($validHits);
            $this->view->query = $queryStr;

            $this->view->searchResults = $searchResults;


            if ($this->fuzzySearch) {
                //look for similar search terms
                if (!empty($queryStr) and (empty($searchResults) or count($searchResults) < 1)) {

                    $terms = SearchPhp_Plugin::fuzzyFindTerms($queryStr, $this->frontendIndex, 3);
                    if (empty($terms) or count($terms) < 1) {
                        $terms = SearchPhp_Plugin::fuzzyFindTerms($queryStr, $this->frontendIndex, 0);
                    }
                    $suggestions = array();

                    if (is_array($terms)) {
                        $counter = 0;
                        foreach ($terms as $term) {
                            $t = $term->text;

                            //check if term can be found for current language
                            if ($this->searchLanguage != null) {
                                if (is_object($this->searchLanguage)) {
                                    $language = $this->searchLanguage->toString();
                                } else {
                                    $language = $this->searchLanguage;
                                }
                                $language = str_replace(array("_", "-"), "", $language);
                            }
                            $hits = null;

                            $query = new Zend_Search_Lucene_Search_Query_Boolean();
                            if ($language != null) {
                                $languageTerm = new Zend_Search_Lucene_Index_Term($language, 'lang');
                                $languageQuery = new Zend_Search_Lucene_Search_Query_Term($languageTerm);
                                $query->addSubquery($languageQuery, true);
                            }

                            if (!empty($category)) {
                                $categoryTerm = new Zend_Search_Lucene_Index_Term($category, 'cat');
                                $categoryQuery = new Zend_Search_Lucene_Search_Query_Term($categoryTerm);
                                $query->addSubquery($categoryQuery, true);
                            }

                            $userQuery = Zend_Search_Lucene_Search_QueryParser::parse($t, 'utf-8');
                            $query->addSubquery($userQuery, true);
                            $hits = $this->frontendIndex->find($query);

                            $validHits = array();
                            if ($this->ownHostOnly and $hits != null) {
                                //get rid of hits from other hosts
                                $currenthost = $_SERVER['HTTP_HOST'];
                                if (count($hits) == 1) {
                                    $url = $hits[0]->getDocument()->getField("url");
                                    if (strpos($url->value, "http://" . $currenthost) !== FALSE || strpos($url->value, "https://" . $currenthost) !== FALSE) {
                                        $validHits[] = $hits[0];
                                    }
                                }
                                for ($i = 0; $i < (count($hits)); $i++) {
                                    $url = $hits[$i]->getDocument()->getField("url");
                                    if (strpos($url->value, "http://" . $currenthost) !== FALSE) {
                                        $validHits[] = $hits[$i];
                                    }
                                }
                            } else {
                                $validHits = $hits;
                            }

                            if (count($validHits) > 0 and!in_array($t, $suggestions)) {

                                $suggestions[] = $t;
                                if ($counter >= 20) break;
                                $counter++;
                            }

                        }
                    }
                    $this->view->suggestions = $suggestions;


                }
            }
        } catch (Exception $e) {
            Logger::log("An Exception occured during search:", Zend_Log::ERR);
            Logger::log($e, Zend_Log::ERR);
            $this->view->searchResults = array();
        }

        if ($this->_getParam("viewscript")) {
            $this->renderScript($this->_getParam("viewscript"));
        }


    }


    /**
     * remove evil stuff from request string
     * @param  string $requestString
     * @return string
     */
    private function cleanRequestString($requestString)
    {

        $queryFromRequest = strip_tags(urldecode($requestString));
        $queryFromRequest = str_replace(array('<', '>', '"', "'", '&'), "", $queryFromRequest);
        return $queryFromRequest;

    }

}