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

class SearchPhp_Tool {  
    /**
     * @return void
     */
    public static function generateSitemap(){
        $sitemapDir = PIMCORE_WEBSITE_PATH."/var/search/sitemap";
        if(is_dir($sitemapDir) and !is_writable($sitemapDir)){
            $sitemapDirAvailable =false;
        } else if(!is_dir($sitemapDir)){
            $sitemapDirAvailable = mkdir($sitemapDir, 0755, true);
            chmod($sitemapDir, 0755);
        } else {
            $sitemapDirAvailable =true;
        }

        if($sitemapDirAvailable){
            $db = Pimcore_Resource_Mysql::get();

                    $hosts = $db->fetchAll("SELECT DISTINCT host from plugin_searchphp_contents");
                    if(is_array($hosts)){

                        //create domain sitemaps
                        foreach($hosts as $row){
                            $host = $row['host'];
                            $data = $db->fetchAll("SELECT * FROM plugin_searchphp_contents WHERE host = '".$host."' AND content != 'canonical' AND content!='noindex' ORDER BY uri", array());
                            $name = str_replace(".","-",$host);
                            $filePath = $sitemapDir . "/sitemap-".$name.".xml";

                            $fh = fopen($filePath, 'w');
                            fwrite($fh,'<?xml version="1.0" encoding="UTF-8"?>'."\r\n");
                            fwrite($fh,'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
                            fwrite($fh,"\r\n");
                            foreach($data as $row){
                                $uri = str_replace("&pimcore_outputfilters_disabled=1","",$row['uri']);
                                $uri = str_replace("?pimcore_outputfilters_disabled=1","",$uri);
                                fwrite($fh,'<url>'."\r\n");
                                fwrite($fh,'    <loc>'.htmlspecialchars($uri,ENT_QUOTES).'</loc>'."\r\n");
                                fwrite($fh,'</url>'."\r\n");
                            }
                            fwrite($fh,'</urlset>'."\r\n");
                            fclose($fh);
                        }

                        //create sitemap index file
                        $filePath = $sitemapDir . "/sitemap.xml";
                        $fh = fopen($filePath, 'w');
                        fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n");
                        fwrite($fh, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
                        fwrite($fh, "\r\n");
                        foreach ($hosts as $row) {
                            $host = $row['host'];
                            $name = str_replace(".", "-", $host);

                            //first host must be main domain - see hint in plugin settings
                            $currenthost = $hosts[0]['host'];
                            fwrite($fh, '<sitemap>' . "\r\n");
                            fwrite($fh, '    <loc>http://' . $currenthost . "/plugin/SearchPhp/frontend/sitemap/?sitemap=sitemap-" . $name . ".xml" . '</loc>' . "\r\n");
                            fwrite($fh, '</sitemap>' . "\r\n");
                        }
                        fwrite($fh, '</sitemapindex>' . "\r\n");
                        fclose($fh);

                    } else {
                        logger::warn("SearchPhp_Tool: could not generate sitemaps, did not find any hosts in index.");
                    }

        } else {
            logger::emerg("SearchPhp_Tool: Cannot generate sitemap. Sitemap directory [ ".$sitemapDir." ]  not available/not writeable and cannot be created");
        }

    }

    

}
